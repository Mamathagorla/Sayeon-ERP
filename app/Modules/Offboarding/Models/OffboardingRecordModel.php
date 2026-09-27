<?php

namespace App\Modules\Offboarding\Models;

use CodeIgniter\Model;

class OffboardingRecordModel extends Model
{
    // Ordered — index+1 is "the next stage" (see nextStatus()). 'rescinded'
    // is a deliberate addition beyond the requested 12 stages: an exit
    // path for a withdrawn resignation or a reversed termination, kept
    // out of the linear sequence since it isn't "next" from anywhere.
    public const STATUSES = [
        'resignation_submitted', 'manager_review', 'hr_approval', 'exit_initiated',
        'handover', 'department_clearances', 'asset_return', 'access_revocation',
        'final_settlement', 'exit_interview', 'final_documents', 'exit_completed',
    ];
    public const RESCINDED = 'rescinded';

    public const STAGE_LABELS = [
        'resignation_submitted' => 'Resignation Submitted',
        'manager_review'        => 'Manager Review',
        'hr_approval'           => 'HR Approval',
        'exit_initiated'        => 'Exit Initiated',
        'handover'              => 'Handover',
        'department_clearances' => 'Department Clearances',
        'asset_return'          => 'Asset Return',
        'access_revocation'     => 'Access Revocation',
        'final_settlement'      => 'Final Settlement',
        'exit_interview'        => 'Exit Interview',
        'final_documents'       => 'Final Documents',
        'exit_completed'        => 'Exit Completed',
        'rescinded'             => 'Rescinded',
    ];

    public const SECTION_STATUSES = ['not_started', 'in_progress', 'completed'];

    public const EXIT_TYPES = ['resignation', 'termination'];

    // Records at these statuses count as "already in progress" — used to
    // block an employee from filing a second resignation while one is
    // still open.
    public const ACTIVE_STATUSES = [
        'resignation_submitted', 'manager_review', 'hr_approval', 'exit_initiated',
        'handover', 'department_clearances', 'asset_return', 'access_revocation',
        'final_settlement', 'exit_interview', 'final_documents',
    ];

    /**
     * Display grouping of the 12 stages into 3 buckets (+ rescinded) for
     * the list page's summary/filter — not used for any workflow logic.
     */
    public const STAGE_GROUPS = [
        'pending'   => ['resignation_submitted', 'manager_review', 'hr_approval'],
        'progress'  => ['exit_initiated', 'handover', 'department_clearances', 'asset_return', 'access_revocation', 'final_settlement', 'exit_interview', 'final_documents'],
        'completed' => ['exit_completed'],
    ];

    public const GROUP_LABELS = [
        'pending'   => 'Pending Approval',
        'progress'  => 'In Progress',
        'completed' => 'Completed',
        'rescinded' => 'Rescinded',
    ];

    public static function groupFor(string $status): string
    {
        foreach (self::STAGE_GROUPS as $group => $statuses) {
            if (in_array($status, $statuses, true)) {
                return $group;
            }
        }

        return 'rescinded';
    }

    protected $table         = 'offboarding_records';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = [
        'employee_profile_id', 'company_id', 'exit_type', 'reason', 'exit_date',
        'last_working_day', 'notice_period_days',
        'handover_status', 'department_clearance_status', 'asset_return_status',
        'access_revocation_status', 'final_settlement_status', 'exit_interview_status',
        'final_document_status', 'status', 'initiated_by',
        'manager_reviewed_by', 'manager_reviewed_at', 'hr_approved_by', 'hr_approved_at',
        'completed_at',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'employee_profile_id' => 'required|integer',
        'company_id'          => 'required|integer',
        'exit_type'           => 'required|in_list[resignation,termination]',
        'exit_date'           => 'required|valid_date',
        'last_working_day'    => 'permit_empty|valid_date',
        'notice_period_days'  => 'permit_empty|integer|greater_than_equal_to[0]',
        'status'              => 'permit_empty|in_list[resignation_submitted,manager_review,hr_approval,exit_initiated,handover,department_clearances,asset_return,access_revocation,final_settlement,exit_interview,final_documents,exit_completed,rescinded]',
    ];

    public function nextStatus(string $current): ?string
    {
        $index = array_search($current, self::STATUSES, true);

        if ($index === false || ! isset(self::STATUSES[$index + 1])) {
            return null;
        }

        return self::STATUSES[$index + 1];
    }

    public function filtered(array $filters = [])
    {
        // tasks_total/tasks_done are correlated subqueries (not a join)
        // so each record still returns exactly one row.
        $builder = $this->select("offboarding_records.*, companies.name as company_name,
                employee_profiles.employee_code as employee_code, employee_profiles.designation as designation,
                employee_profiles.date_of_joining as date_of_joining,
                employee_profiles.reporting_manager_id as reporting_manager_id, employee_profiles.user_id as employee_user_id,
                users.name as employee_name, manager.name as manager_name, departments.name as department_name,
                (SELECT COUNT(*) FROM offboarding_tasks ot WHERE ot.offboarding_record_id = offboarding_records.id) as tasks_total,
                (SELECT COUNT(*) FROM offboarding_tasks ot WHERE ot.offboarding_record_id = offboarding_records.id AND ot.status = 'completed') as tasks_done")
            ->join('employee_profiles', 'employee_profiles.id = offboarding_records.employee_profile_id')
            ->join('companies', 'companies.id = offboarding_records.company_id')
            ->join('users', 'users.id = employee_profiles.user_id')
            ->join('users as manager', 'manager.id = employee_profiles.reporting_manager_id', 'left')
            ->join('departments', 'departments.id = employee_profiles.department_id', 'left');

        // array_key_exists (not empty()) — same convention as every
        // other module's filtered(): 0 must match zero rows, not fall
        // through to "no restriction".
        if (array_key_exists('company_id', $filters)) {
            $builder->where('offboarding_records.company_id', $filters['company_id']);
        }
        if (! empty($filters['status'])) {
            $builder->where('offboarding_records.status', $filters['status']);
        }
        if (! empty($filters['exit_type'])) {
            $builder->where('offboarding_records.exit_type', $filters['exit_type']);
        }
        if (! empty($filters['stage_group']) && isset(self::STAGE_GROUPS[$filters['stage_group']])) {
            $builder->whereIn('offboarding_records.status', self::STAGE_GROUPS[$filters['stage_group']]);
        }
        if (! empty($filters['employee_user_id'])) {
            $builder->where('employee_profiles.user_id', $filters['employee_user_id']);
        }

        return $builder->orderBy('offboarding_records.created_at', 'DESC');
    }

    public function withRelations(int $id): ?array
    {
        return $this->filtered()->where('offboarding_records.id', $id)->first();
    }

    public function hasActiveExit(int $employeeProfileId): bool
    {
        return $this->where('employee_profile_id', $employeeProfileId)
            ->whereIn('status', self::ACTIVE_STATUSES)
            ->countAllResults() > 0;
    }

    public function countsByStatus(?int $companyScope): array
    {
        $builder = $this->select('status, COUNT(*) as total')->groupBy('status');

        if ($companyScope !== null) {
            $builder->where('company_id', $companyScope);
        }

        $rows   = array_column($builder->findAll(), 'total', 'status');
        $counts = [];

        foreach (array_merge(self::STATUSES, [self::RESCINDED]) as $status) {
            $counts[$status] = (int) ($rows[$status] ?? 0);
        }

        return $counts;
    }
}
