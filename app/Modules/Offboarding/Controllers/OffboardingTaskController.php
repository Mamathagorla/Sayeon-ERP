<?php

namespace App\Modules\Offboarding\Controllers;

use App\Controllers\BaseController;
use App\Modules\Offboarding\Models\OffboardingRecordModel;
use App\Modules\Offboarding\Models\OffboardingTaskModel;

/**
 * Completing a single checklist item — split out from OffboardingController
 * for the same reason Onboarding splits this out: task actions are
 * authorized differently from the main record. HR can act on any task;
 * Manager/Company Admin (IT/Admin)/Accountant (Finance)/Employee may only
 * touch tasks whose owner_role matches their own role, and an Employee is
 * additionally restricted to tasks on *their own* exit record — unlike
 * the other three roles, "Employee" isn't a company-wide reviewer role,
 * so a role-match alone isn't enough to keep them off other people's
 * exit records.
 */
class OffboardingTaskController extends BaseController
{
    private const HR_ROLES = ['hr', 'super_admin'];

    protected OffboardingRecordModel $recordModel;
    protected OffboardingTaskModel $taskModel;

    public function __construct()
    {
        $this->recordModel = new OffboardingRecordModel();
        $this->taskModel   = new OffboardingTaskModel();
    }

    public function update(int $recordId, int $taskId)
    {
        $record = $this->recordModel->withRelations($recordId);

        if ($record === null || $this->outOfScope(self::COMPANY_SCOPED_ROLES, $record['company_id'])) {
            return redirect()->to('/hr/offboarding')->with('error', 'Offboarding record not found.');
        }

        $task = $this->taskModel->find($taskId);

        if ($task === null || (int) $task['offboarding_record_id'] !== $recordId) {
            return redirect()->to('/hr/offboarding/' . $recordId)->with('error', 'Checklist item not found.');
        }

        if (! $this->canActOn($task, $record)) {
            return redirect()->to('/hr/offboarding/' . $recordId)->with('error', 'You can only update your own section\'s checklist items.');
        }

        $status = $this->request->getPost('status');

        if (! in_array($status, OffboardingTaskModel::STATUSES, true)) {
            return redirect()->to('/hr/offboarding/' . $recordId)->with('error', 'Invalid task status.');
        }

        $data = [
            'status' => $status,
            'notes'  => $this->request->getPost('notes') ?: null,
        ];

        if ($status === 'completed') {
            $data['completed_at'] = date('Y-m-d H:i:s');
            $data['completed_by'] = $this->currentUserId();
        } else {
            $data['completed_at'] = null;
            $data['completed_by'] = null;
        }

        $this->taskModel->update($taskId, $data);

        // Keep the record's 7 section-status fields in sync with the
        // checklist — the only place these columns are ever written, so
        // they can't drift from the tasks that back them.
        $sectionField = OffboardingTaskModel::TASK_TYPE_TO_SECTION_FIELD[$task['task_type']] ?? null;
        if ($sectionField !== null) {
            $this->recordModel->update($recordId, [
                $sectionField => $this->taskModel->sectionStatusFor($recordId, $task['task_type']),
            ]);
        }

        $this->syncStageWithChecklist($recordId);

        $this->logActivity('offboarding', 'update', $recordId, 'Updated checklist item "' . $task['title'] . '" for ' . $record['employee_name']);

        return redirect()->to('/hr/offboarding/' . $recordId)->with('success', 'Checklist item updated.');
    }

    /**
     * Moves the record's stage forward as its checklist gets finished: the
     * stage becomes the first one whose tasks aren't all done. Forward
     * only, never auto-completes the exit (HR still sets "Exit Completed"
     * manually), and leaves rescinded/completed records alone. Stages
     * with no tasks of their own pass automatically.
     */
    private function syncStageWithChecklist(int $recordId): void
    {
        $record = $this->recordModel->find($recordId);
        $order  = OffboardingRecordModel::STATUSES;
        $currentIdx = array_search($record['status'] ?? '', $order, true);

        if ($currentIdx === false || $record['status'] === 'exit_completed') {
            return;
        }

        $stageTaskType = [
            'manager_review' => 'manager_review', 'hr_approval' => 'hr_approval',
            'handover' => 'handover', 'department_clearances' => 'department_clearance',
            'asset_return' => 'asset_return', 'access_revocation' => 'access_revocation',
            'final_settlement' => 'final_settlement', 'exit_interview' => 'exit_interview',
            'final_documents' => 'final_documents',
        ];

        $open = [];
        foreach ($this->taskModel->where('offboarding_record_id', $recordId)->findAll() as $t) {
            if (! in_array($t['status'], ['completed', 'skipped'], true)) {
                $open[$t['task_type']] = true;
            }
        }

        $targetIdx = array_search('final_documents', $order, true);
        foreach ($order as $i => $stage) {
            if ($stage === 'exit_completed') {
                break;
            }
            if (isset($stageTaskType[$stage]) && isset($open[$stageTaskType[$stage]])) {
                $targetIdx = $i;
                break;
            }
        }

        if ($targetIdx <= $currentIdx) {
            return;
        }

        $target = $order[$targetIdx];
        $data   = ['status' => $target];
        $now    = date('Y-m-d H:i:s');

        // Same stamps OffboardingController::updateStatus() applies when
        // a stage is advanced by hand.
        if ($targetIdx > array_search('manager_review', $order, true) && $record['manager_reviewed_at'] === null) {
            $data['manager_reviewed_by'] = $this->currentUserId();
            $data['manager_reviewed_at'] = $now;
        }
        if ($targetIdx > array_search('hr_approval', $order, true) && $record['hr_approved_at'] === null) {
            $data['hr_approved_by'] = $this->currentUserId();
            $data['hr_approved_at'] = $now;
        }

        $this->recordModel->update($recordId, $data);
    }

    private function canActOn(array $task, array $record): bool
    {
        $roleSlug = session('roleSlug');

        if (in_array($roleSlug, self::HR_ROLES, true)) {
            return true;
        }

        if ($task['owner_role'] !== $roleSlug) {
            return false;
        }

        // Manager/Admin/Accountant act on their section across their
        // whole company's pipeline (company scope already checked by
        // the caller); Employee only ever on their own exit record.
        if ($roleSlug === 'employee') {
            return (int) $record['employee_user_id'] === (int) $this->currentUserId();
        }

        return true;
    }
}
