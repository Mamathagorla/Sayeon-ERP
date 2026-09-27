<?php

namespace App\Modules\Compliance\Models;

use CodeIgniter\Model;

class ComplianceItemModel extends Model
{
    protected $table            = 'compliance_items';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';

    public const STATUSES   = ['pending', 'in_progress', 'filed', 'overdue'];
    public const RECURRENCES = ['none', 'monthly', 'quarterly', 'half_yearly', 'annually'];

    protected $allowedFields = [
        'company_id', 'compliance_type_id', 'title', 'regulator', 'period', 'due_date', 'recurrence',
        'responsible_user_id', 'status', 'reminder_days_before', 'notes',
        'filed_at', 'previous_item_id', 'created_by',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'company_id'          => 'required|integer',
        'compliance_type_id'  => 'required|integer',
        'due_date'            => 'required|valid_date',
        'recurrence'          => 'required|in_list[none,monthly,quarterly,half_yearly,annually]',
        'reminder_days_before' => 'permit_empty|integer|greater_than_equal_to[0]',
        'regulator'           => 'permit_empty|max_length[150]',
        'period'              => 'permit_empty|max_length[50]',
    ];

    /**
     * Any pending/in_progress item whose due date has passed is
     * flipped to 'overdue'. Cheap enough to run on every index-page
     * load for now; Phase 12's notification cron will call the same
     * method on a schedule instead of relying on page views.
     */
    public function refreshOverdueStatuses(): void
    {
        $this->whereIn('status', ['pending', 'in_progress'])
            ->where('due_date <', date('Y-m-d'))
            ->set(['status' => 'overdue'])
            ->update();
    }

    public function filtered(array $filters = [])
    {
        $builder = $this->select('compliance_items.*, companies.name as company_name,
                compliance_types.name as type_name, users.name as responsible_name')
            ->join('companies', 'companies.id = compliance_items.company_id')
            ->join('compliance_types', 'compliance_types.id = compliance_items.compliance_type_id')
            ->join('users', 'users.id = compliance_items.responsible_user_id', 'left');

        // array_key_exists (not empty()) — 0 means "Company Admin has no
        // employee_profiles company assigned yet" and must match zero
        // rows, not fall through to showing every company's items.
        if (array_key_exists('company_id', $filters)) {
            $builder->where('compliance_items.company_id', $filters['company_id']);
        }
        if (! empty($filters['compliance_type_id'])) {
            is_array($filters['compliance_type_id'])
                ? $builder->whereIn('compliance_items.compliance_type_id', $filters['compliance_type_id'])
                : $builder->where('compliance_items.compliance_type_id', $filters['compliance_type_id']);
        }
        if (! empty($filters['status'])) {
            is_array($filters['status'])
                ? $builder->whereIn('compliance_items.status', $filters['status'])
                : $builder->where('compliance_items.status', $filters['status']);
        }
        if (! empty($filters['responsible_user_id'])) {
            $builder->where('compliance_items.responsible_user_id', $filters['responsible_user_id']);
        }

        return $builder->orderBy('compliance_items.due_date', 'ASC');
    }

    public function withRelations(int $id): ?array
    {
        return $this->filtered()->where('compliance_items.id', $id)->first();
    }

    /**
     * Items overdue or due within the next N days — feeds the
     * Dashboard's "Compliance Alerts" card. Pass $companyId for Company
     * Admin / Super Admin's active-company dashboard scoping.
     */
    public function alerts(int $withinDays = 7, int $limit = 10, ?int $companyId = null): array
    {
        $builder = $this->filtered($companyId !== null ? ['company_id' => $companyId] : [])
            ->whereIn('compliance_items.status', ['pending', 'in_progress', 'overdue'])
            ->where('compliance_items.due_date <=', date('Y-m-d', strtotime("+{$withinDays} days")));

        return $builder->findAll($limit);
    }

    public function alertCount(int $withinDays = 7, ?int $companyId = null): int
    {
        $builder = $this->whereIn('status', ['pending', 'in_progress', 'overdue'])
            ->where('due_date <=', date('Y-m-d', strtotime("+{$withinDays} days")));

        if ($companyId !== null) {
            $builder->where('company_id', $companyId);
        }

        return $builder->countAllResults();
    }
}
