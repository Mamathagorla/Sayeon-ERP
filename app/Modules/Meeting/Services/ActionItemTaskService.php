<?php

namespace App\Modules\Meeting\Services;

use App\Modules\Meeting\Models\MeetingActionItemModel;
use App\Modules\Meeting\Models\MeetingModel;
use App\Modules\Task\Models\ProjectModel;
use App\Modules\Task\Models\TaskModel;
use App\Modules\Task\Models\TaskStatusHistoryModel;

/**
 * Cross-module logic (Meeting → Task) lives here rather than in either
 * module's Model, per the "Services own cross-module workflows"
 * convention (see Architecture §2). MeetingController and
 * ActionItemController both call this instead of reaching into
 * TaskModel directly.
 */
class ActionItemTaskService
{
    protected MeetingModel $meetingModel;
    protected MeetingActionItemModel $actionItemModel;
    protected TaskModel $taskModel;
    protected TaskStatusHistoryModel $historyModel;

    public function __construct()
    {
        $this->meetingModel    = new MeetingModel();
        $this->actionItemModel = new MeetingActionItemModel();
        $this->taskModel       = new TaskModel();
        $this->historyModel    = new TaskStatusHistoryModel();
    }

    /**
     * Turns a meeting action item into a real, trackable Task and links
     * the two records both ways (task carries no back-reference column
     * by design — the action item's linked_task_id is the source of truth).
     *
     * @throws \RuntimeException if the action item or its meeting is missing.
     */
    public function convert(int $actionItemId, int $departmentId, int $currentUserId, int $projectId = 0): array
    {
        $actionItem = $this->actionItemModel->find($actionItemId);

        if ($actionItem === null) {
            throw new \RuntimeException('Action item not found.');
        }

        if (! empty($actionItem['linked_task_id'])) {
            throw new \RuntimeException('This action item has already been converted to a task.');
        }

        $meeting = $this->meetingModel->find($actionItem['meeting_id']);

        if ($meeting === null) {
            throw new \RuntimeException('Parent meeting not found.');
        }

        // Every task must belong to a project of its own company (TaskModel's validation rule). Without this check
        // the insert silently failed and the history/link writes below then hit a foreign-key error (HTTP 500).
        $project = $projectId > 0 ? (new ProjectModel())->find($projectId) : null;

        if ($project === null || (int) $project['company_id'] !== (int) $meeting['company_id']) {
            throw new \RuntimeException("Choose a project from this meeting's company to convert this action item into a task.");
        }

        $db = db_connect();
        $db->transStart();

        $taskId = $this->taskModel->insert([
            'title'         => 'Follow-up: ' . $actionItem['description'],
            'description'   => "Auto-created from meeting \"{$meeting['title']}\" (held {$meeting['meeting_date']}).",
            'company_id'    => $meeting['company_id'],
            'department_id' => $departmentId,
            'project_id'    => $projectId,
            'priority'      => 'medium',
            'assigned_to'   => $actionItem['assigned_to'],
            'created_by'    => $currentUserId,
            'due_date'      => $actionItem['due_date'],
            'status'        => 'new',
        ]);

        if ($taskId === false) {
            $db->transRollback();

            throw new \RuntimeException('The task could not be created: ' . implode(' ', $this->taskModel->errors()));
        }

        $this->historyModel->record((int) $taskId, null, 'new', $currentUserId);

        $this->actionItemModel->update($actionItemId, ['linked_task_id' => $taskId]);

        $db->transComplete();

        if (! $db->transStatus()) {
            throw new \RuntimeException('The task could not be created. Nothing was saved.');
        }

        // Same notice TaskController sends when a task is assigned.
        if (! empty($actionItem['assigned_to']) && (int) $actionItem['assigned_to'] !== $currentUserId) {
            (new \App\Modules\Notification\Services\NotificationService())->notify(
                (int) $actionItem['assigned_to'],
                'task_assigned',
                'New task assigned: Follow-up: ' . $actionItem['description'],
                'You were assigned to "Follow-up: ' . $actionItem['description'] . '" from the meeting "' . $meeting['title'] . '".',
                'task',
                (int) $taskId
            );
        }

        return ['task_id' => $taskId];
    }
}
