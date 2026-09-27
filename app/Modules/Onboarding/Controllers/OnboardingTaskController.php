<?php

namespace App\Modules\Onboarding\Controllers;

use App\Controllers\BaseController;
use App\Modules\Onboarding\Models\OnboardingRecordModel;
use App\Modules\Onboarding\Models\OnboardingTaskModel;

/**
 * Completing a single checklist item — split out from OnboardingController
 * (same reasoning as Meeting's ParticipantController/ActionItemController
 * split) since task actions are authorized differently from the main
 * record: HR can act on any task, but Manager/Company Admin (IT/Admin)/
 * Accountant (Finance) may only touch tasks whose owner_role matches
 * their own role — this is what keeps them "confined to their relevant
 * section" while still sharing the onboarding.edit permission slug.
 */
class OnboardingTaskController extends BaseController
{
    private const HR_ROLES = ['hr', 'super_admin'];

    protected OnboardingRecordModel $recordModel;
    protected OnboardingTaskModel $taskModel;

    public function __construct()
    {
        $this->recordModel = new OnboardingRecordModel();
        $this->taskModel   = new OnboardingTaskModel();
    }

    public function update(int $recordId, int $taskId)
    {
        $record = $this->recordModel->find($recordId);

        if ($record === null || $this->outOfScope(self::COMPANY_SCOPED_ROLES, $record['company_id'])) {
            return redirect()->to('/hr/onboarding')->with('error', 'Onboarding record not found.');
        }

        $task = $this->taskModel->find($taskId);

        if ($task === null || (int) $task['onboarding_record_id'] !== $recordId) {
            return redirect()->to('/hr/onboarding/' . $recordId)->with('error', 'Checklist item not found.');
        }

        if (! $this->canActOn($task)) {
            return redirect()->to('/hr/onboarding/' . $recordId)->with('error', 'You can only update your own section\'s checklist items.');
        }

        $status = $this->request->getPost('status');

        if (! in_array($status, OnboardingTaskModel::STATUSES, true)) {
            return redirect()->to('/hr/onboarding/' . $recordId)->with('error', 'Invalid task status.');
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
        $this->logActivity('onboarding', 'update', $recordId, 'Updated checklist item "' . $task['title'] . '" for ' . $record['candidate_name']);

        return redirect()->to('/hr/onboarding/' . $recordId)->with('success', 'Checklist item updated.');
    }

    /**
     * HR (and Super Admin) can act on any task; everyone else only on
     * tasks whose owner_role matches their own session role — company
     * scope is already enforced by the caller before this runs.
     */
    private function canActOn(array $task): bool
    {
        $roleSlug = session('roleSlug');

        return in_array($roleSlug, self::HR_ROLES, true) || $task['owner_role'] === $roleSlug;
    }
}
