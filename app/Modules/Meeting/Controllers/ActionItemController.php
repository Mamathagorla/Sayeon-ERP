<?php

namespace App\Modules\Meeting\Controllers;

use App\Controllers\BaseController;
use App\Modules\Meeting\Models\MeetingActionItemModel;
use App\Modules\Meeting\Models\MeetingModel;
use App\Modules\Meeting\Services\ActionItemTaskService;

class ActionItemController extends BaseController
{
    protected MeetingActionItemModel $actionItemModel;
    protected MeetingModel $meetingModel;
    protected ActionItemTaskService $taskService;

    public function __construct()
    {
        $this->actionItemModel = new MeetingActionItemModel();
        $this->meetingModel    = new MeetingModel();
        $this->taskService     = new ActionItemTaskService();
    }

    public function store(int $meetingId)
    {
        if ($this->authorizedMeeting($meetingId) === null) {
            return redirect()->to('/meetings')->with('error', 'Meeting not found.');
        }

        if (! $this->request->getPost('description')) {
            return redirect()->back()->with('error', 'Action item description is required.');
        }

        $this->actionItemModel->insert([
            'meeting_id'  => $meetingId,
            'description' => $this->request->getPost('description'),
            'assigned_to' => $this->request->getPost('assigned_to') ?: null,
            'due_date'    => $this->request->getPost('due_date') ?: null,
            'status'      => 'pending',
        ]);

        return redirect()->to('/meetings/' . $meetingId)->with('success', 'Action item added.');
    }

    public function updateStatus(int $meetingId, int $itemId)
    {
        if ($this->authorizedMeeting($meetingId) === null) {
            return redirect()->to('/meetings')->with('error', 'Meeting not found.');
        }

        $item = $this->actionItemModel->find($itemId);

        if ($item && (int) $item['meeting_id'] === $meetingId) {
            $this->actionItemModel->update($itemId, ['status' => $this->request->getPost('status')]);
        }

        return redirect()->to('/meetings/' . $meetingId);
    }

    /**
     * "Follow-up Tasks" from the spec: turns this action item into a
     * real Task via ActionItemTaskService so it shows up in the Task
     * module's list/dashboard/reports, not just buried in meeting notes.
     */
    public function convertToTask(int $meetingId, int $itemId)
    {
        if ($this->authorizedMeeting($meetingId) === null) {
            return redirect()->to('/meetings')->with('error', 'Meeting not found.');
        }

        $departmentId = (int) $this->request->getPost('department_id');

        if ($departmentId === 0) {
            return redirect()->to('/meetings/' . $meetingId)->with('error', 'Choose a department to convert this action item into a task.');
        }

        try {
            $result = $this->taskService->convert($itemId, $departmentId, (int) $this->currentUserId());
        } catch (\RuntimeException $e) {
            return redirect()->to('/meetings/' . $meetingId)->with('error', $e->getMessage());
        }

        $this->logActivity('meeting', 'update', $meetingId, 'Converted action item #' . $itemId . ' to task #' . $result['task_id']);

        return redirect()->to('/tasks/' . $result['task_id'])->with('success', 'Follow-up task created from action item.');
    }

    public function delete(int $meetingId, int $itemId)
    {
        if ($this->authorizedMeeting($meetingId) === null) {
            return redirect()->to('/meetings')->with('error', 'Meeting not found.');
        }

        $item = $this->actionItemModel->find($itemId);

        if ($item && (int) $item['meeting_id'] === $meetingId) {
            $this->actionItemModel->delete($itemId);
        }

        return redirect()->to('/meetings/' . $meetingId)->with('success', 'Action item removed.');
    }

    /**
     * Same purpose as TaskController::authorizedTask() — every action
     * here takes a meeting id from the URL, so each one re-checks that
     * the meeting is actually within the viewer's company scope before
     * touching its action items.
     */
    private function authorizedMeeting(int $meetingId): ?array
    {
        $meeting = $this->meetingModel->find($meetingId);

        if ($meeting === null || $this->outOfScope(self::COMPANY_SCOPED_ROLES, $meeting['company_id'])) {
            return null;
        }

        return $meeting;
    }
}
