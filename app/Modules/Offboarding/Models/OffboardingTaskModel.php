<?php

namespace App\Modules\Offboarding\Models;

use CodeIgniter\Model;

class OffboardingTaskModel extends Model
{
    public const TASK_TYPES = [
        'manager_review', 'hr_approval', 'handover', 'department_clearance',
        'asset_return', 'access_revocation', 'final_settlement', 'exit_interview',
        'final_documents', 'other',
    ];
    // 'employee' is an owner_role Onboarding's tasks didn't need (a
    // pre-hire candidate isn't a system user); an exiting employee is,
    // and owns tasks like handover notes/asset return themselves.
    public const OWNER_ROLES = ['hr', 'manager', 'admin', 'accountant', 'employee'];
    public const STATUSES    = ['pending', 'in_progress', 'completed', 'skipped'];

    public const OWNER_ROLE_LABELS = [
        'hr'         => 'HR',
        'manager'    => 'Manager',
        'admin'      => 'IT / Admin',
        'accountant' => 'Finance',
        'employee'   => 'Employee',
    ];

    /**
     * Maps a task_type to the offboarding_records column it feeds — used
     * by OffboardingTaskController to recompute that section's summary
     * status after any task in it changes. 'manager_review'/'hr_approval'
     * are pipeline gate-tasks (see OffboardingRecordModel::STATUSES),
     * not one of the 7 requested section fields, so they map to nothing.
     */
    public const TASK_TYPE_TO_SECTION_FIELD = [
        'handover'             => 'handover_status',
        'department_clearance' => 'department_clearance_status',
        'asset_return'         => 'asset_return_status',
        'access_revocation'    => 'access_revocation_status',
        'final_settlement'     => 'final_settlement_status',
        'exit_interview'       => 'exit_interview_status',
        'final_documents'      => 'final_document_status',
    ];

    /**
     * Auto-created for every new offboarding record — covers every role's
     * stated responsibility in the request (Manager: review + handover +
     * team clearance; Accountant: final settlement; Company Admin: assets
     * + IT/access; Employee: handover prep + asset return; HR: approval,
     * exit interview, final documents).
     */
    public const DEFAULT_TASKS = [
        ['type' => 'manager_review',        'title' => 'Review resignation and provide recommendation', 'owner_role' => 'manager'],
        ['type' => 'hr_approval',           'title' => 'Review and approve/reject the exit request', 'owner_role' => 'hr'],
        ['type' => 'handover',              'title' => 'Prepare handover notes', 'owner_role' => 'employee'],
        ['type' => 'handover',              'title' => 'Review and accept handover', 'owner_role' => 'manager'],
        ['type' => 'department_clearance',  'title' => 'Obtain department/team clearance', 'owner_role' => 'manager'],
        ['type' => 'asset_return',          'title' => 'Return company assets (laptop, ID card, etc.)', 'owner_role' => 'employee'],
        ['type' => 'asset_return',          'title' => 'Confirm assets received', 'owner_role' => 'admin'],
        ['type' => 'access_revocation',     'title' => 'Revoke system access, email & logins', 'owner_role' => 'admin'],
        ['type' => 'final_settlement',      'title' => 'Process final settlement & dues', 'owner_role' => 'accountant'],
        ['type' => 'exit_interview',        'title' => 'Conduct exit interview', 'owner_role' => 'hr'],
        ['type' => 'final_documents',       'title' => 'Issue relieving letter & experience certificate', 'owner_role' => 'hr'],
    ];

    /**
     * Pure display grouping — the checklist read by WHERE it sits in the
     * exit process rather than WHO owns it (owner_role is still shown
     * per task). Not used for any validation/workflow logic. "Exit
     * Completed" has no tasks of its own: it's the record's final
     * status, rendered by the view as the last step.
     */
    public const PHASES = [
        'Exit Request & Approval'      => ['manager_review', 'hr_approval'],
        'Handover & Transition'        => ['handover'],
        'Clearance & Asset Return'     => ['department_clearance', 'asset_return'],
        'Access Revocation'            => ['access_revocation'],
        'Final Settlement & Documents' => ['final_settlement', 'exit_interview', 'final_documents', 'other'],
        'Exit Completed'               => [],
    ];

    public static function phaseFor(string $taskType): string
    {
        foreach (self::PHASES as $phase => $types) {
            if (in_array($taskType, $types, true)) {
                return $phase;
            }
        }

        return 'Final Settlement & Documents';
    }

    /**
     * Same relationships as PHASES above, keyed by OffboardingPhaseModel's
     * stable legacy_key instead of a display name — see
     * OnboardingTaskModel::TASK_TYPE_PHASE_KEY for why. 'Exit Completed'
     * has no task types mapped to it here either.
     */
    public const TASK_TYPE_PHASE_KEY = [
        'manager_review'        => 'exit_request_approval',
        'hr_approval'           => 'exit_request_approval',
        'handover'               => 'handover_transition',
        'department_clearance'   => 'clearance_asset_return',
        'asset_return'            => 'clearance_asset_return',
        'access_revocation'       => 'access_revocation',
        'final_settlement'        => 'final_settlement_documents',
        'exit_interview'          => 'final_settlement_documents',
        'final_documents'         => 'final_settlement_documents',
        'other'                   => 'final_settlement_documents',
    ];

    protected $table         = 'offboarding_tasks';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = [
        'offboarding_record_id', 'task_type', 'phase_id', 'title', 'owner_role', 'assigned_to',
        'status', 'due_date', 'completed_at', 'completed_by', 'notes',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'offboarding_record_id' => 'required|integer',
        'task_type'             => 'required|in_list[manager_review,hr_approval,handover,department_clearance,asset_return,access_revocation,final_settlement,exit_interview,final_documents,other]',
        'title'                 => 'required|max_length[150]',
        'owner_role'            => 'required|in_list[hr,manager,admin,accountant,employee]',
        'status'                => 'permit_empty|in_list[pending,in_progress,completed,skipped]',
        'due_date'              => 'permit_empty|valid_date',
    ];

    public function forRecord(int $offboardingRecordId): array
    {
        // phases.name is joined in as phase_name purely for display —
        // see OnboardingTaskModel::forRecord()'s equivalent comment.
        return $this->select('offboarding_tasks.*, offboarding_phases.name as phase_name')
            ->join('offboarding_phases', 'offboarding_phases.id = offboarding_tasks.phase_id', 'left')
            ->where('offboarding_record_id', $offboardingRecordId)
            ->orderBy('FIELD(offboarding_tasks.task_type,' . implode(',', array_map(static fn ($t) => "'{$t}'", self::TASK_TYPES)) . ')', '', false)
            ->findAll();
    }

    public function seedDefaultTasks(int $offboardingRecordId): void
    {
        $phaseModel = new OffboardingPhaseModel();

        $rows = array_map(static function (array $t) use ($phaseModel) {
            return [
                'offboarding_record_id' => $offboardingRecordId,
                'task_type'              => $t['type'],
                // Looked up by the stable legacy_key, not a hardcoded id
                // — null (falls into the checklist's "Other" bucket) if
                // that phase has since been deactivated or deleted.
                'phase_id'               => $phaseModel->idForLegacyKey(self::TASK_TYPE_PHASE_KEY[$t['type']] ?? ''),
                'title'                  => $t['title'],
                'owner_role'             => $t['owner_role'],
            ];
        }, self::DEFAULT_TASKS);

        $this->insertBatch($rows);
    }

    /**
     * completed if every task of this type for the record is completed
     * (or skipped), in_progress if any has started, else not_started.
     * Skipped ones under 'other' never map to a section, so this is
     * only ever called for the 7 mapped task_types (see caller).
     */
    public function sectionStatusFor(int $offboardingRecordId, string $taskType): string
    {
        $tasks = $this->where('offboarding_record_id', $offboardingRecordId)
            ->where('task_type', $taskType)
            ->findAll();

        if ($tasks === []) {
            return 'not_started';
        }

        $allDone = true;
        $anyStarted = false;

        foreach ($tasks as $t) {
            if (! in_array($t['status'], ['completed', 'skipped'], true)) {
                $allDone = false;
            }
            if ($t['status'] !== 'pending') {
                $anyStarted = true;
            }
        }

        if ($allDone) {
            return 'completed';
        }

        return $anyStarted ? 'in_progress' : 'not_started';
    }

    /**
     * Pending/in-progress tasks owned by $role across every offboarding
     * record the viewer can see — powers each role's "My Tasks" widget,
     * same as Onboarding's equivalent.
     */
    public function pendingForRole(string $role, ?int $companyScope, ?int $onlyUserId = null): array
    {
        $builder = $this->select('offboarding_tasks.*, offboarding_records.employee_profile_id, offboarding_records.company_id, users.name as employee_name')
            ->join('offboarding_records', 'offboarding_records.id = offboarding_tasks.offboarding_record_id')
            ->join('employee_profiles', 'employee_profiles.id = offboarding_records.employee_profile_id')
            ->join('users', 'users.id = employee_profiles.user_id')
            ->where('offboarding_tasks.owner_role', $role)
            ->whereIn('offboarding_tasks.status', ['pending', 'in_progress']);

        if ($companyScope !== null) {
            $builder->where('offboarding_records.company_id', $companyScope);
        }
        // Employee's own "My Tasks" must never include another
        // employee's exit tasks even though owner_role matches.
        if ($onlyUserId !== null) {
            $builder->where('employee_profiles.user_id', $onlyUserId);
        }

        return $builder->orderBy('offboarding_tasks.due_date', 'ASC')->findAll();
    }
}
