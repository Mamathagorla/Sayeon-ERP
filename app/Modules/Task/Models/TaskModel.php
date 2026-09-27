<?php

namespace App\Modules\Task\Models;

use CodeIgniter\Model;

class TaskModel extends Model
{
    protected $table            = 'tasks';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = true;

    public const STATUSES = ['new', 'assigned', 'in_progress', 'waiting', 'review', 'completed', 'on_hold', 'cancelled'];
    public const PRIORITIES = ['low', 'medium', 'high', 'urgent'];

    protected $allowedFields = [
        'uuid', 'title', 'description', 'company_id', 'department_id', 'project_id',
        'priority', 'assigned_to', 'created_by', 'start_date', 'due_date',
        'status', 'completed_at',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    protected $validationRules = [
        'title'         => 'required|min_length[2]|max_length[200]',
        'company_id'    => 'required|integer',
        'department_id' => 'required|integer',
        // Every task must belong to a project (see TaskController::
        // projectMatchesCompany() for the same-company check this alone
        // can't express). The tasks.project_id column itself stays
        // nullable in the DB — one pre-existing task predates this rule
        // (see erp_db seed data) and forcing NOT NULL would break it;
        // this validation rule is what actually enforces "required" for
        // every task created/edited from here on.
        'project_id'    => 'required|integer',
        'priority'      => 'required|in_list[low,medium,high,urgent]',
        'status'        => 'required|in_list[new,assigned,in_progress,waiting,review,completed,on_hold,cancelled]',
        'start_date'    => 'permit_empty|valid_date',
        'due_date'      => 'permit_empty|valid_date',
    ];

    protected $beforeInsert = ['assignUuid'];

    protected function assignUuid(array $data): array
    {
        if (empty($data['data']['uuid'])) {
            $data['data']['uuid'] = $this->generateUuidV4();
        }

        return $data;
    }

    private function generateUuidV4(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }

    /**
     * Main list query with joins for readable columns, filterable
     * by company/department/status/assignee/priority — powers both
     * the Task index page and (later) the dashboard/report queries.
     */
    public function filtered(array $filters = [])
    {
        $builder = $this->select('tasks.*, companies.name as company_name, departments.name as department_name,
                assignee.name as assignee_name, creator.name as creator_name, projects.name as project_name')
            ->join('companies', 'companies.id = tasks.company_id')
            ->join('departments', 'departments.id = tasks.department_id')
            ->join('users as assignee', 'assignee.id = tasks.assigned_to', 'left')
            ->join('users as creator', 'creator.id = tasks.created_by', 'left')
            // left join: the one pre-existing task without a project (see
            // the validationRules note above) must still show up in lists.
            ->join('projects', 'projects.id = tasks.project_id', 'left');

        // array_key_exists (not empty()) — 0 means "Company Admin has no
        // employee_profiles company assigned yet" and must match zero
        // rows, not fall through to showing every company's tasks.
        if (array_key_exists('company_id', $filters)) {
            $builder->where('tasks.company_id', $filters['company_id']);
        }
        if (! empty($filters['department_id'])) {
            $builder->where('tasks.department_id', $filters['department_id']);
        }
        if (! empty($filters['project_id'])) {
            $builder->where('tasks.project_id', $filters['project_id']);
        }
        if (! empty($filters['status'])) {
            // Array (multi-select filter) vs a single exact status —
            // same convention as OnboardingRecordModel::filtered().
            is_array($filters['status'])
                ? $builder->whereIn('tasks.status', $filters['status'])
                : $builder->where('tasks.status', $filters['status']);
        }
        if (! empty($filters['priority'])) {
            is_array($filters['priority'])
                ? $builder->whereIn('tasks.priority', $filters['priority'])
                : $builder->where('tasks.priority', $filters['priority']);
        }
        if (! empty($filters['assigned_to'])) {
            is_array($filters['assigned_to'])
                ? $builder->whereIn('tasks.assigned_to', $filters['assigned_to'])
                : $builder->where('tasks.assigned_to', $filters['assigned_to']);
        }
        if (! empty($filters['overdue'])) {
            $builder->where('tasks.due_date <', date('Y-m-d'))
                ->whereNotIn('tasks.status', ['completed', 'cancelled']);
        }
        // 'active' isn't a real status value (see STATUSES) — it's the
        // same "not completed/cancelled" bucket dashboardCounts()
        // already reports as one of the 3 headline counts, exposed here
        // so the dashboard's Task Status chart can link straight to the
        // matching task list instead of only being able to filter by
        // one exact status.
        if (! empty($filters['active'])) {
            $builder->whereNotIn('tasks.status', ['completed', 'cancelled']);
        }

        return $builder->orderBy('tasks.due_date', 'ASC');
    }

    /**
     * Open (non-deleted) task count per project, for the Projects list
     * page's "Tasks" column. Returns [project_id => count] — only for
     * the ids actually passed in, so callers can merge it straight into
     * their already-company-scoped project rows without this method
     * needing its own scope check.
     */
    public function countsByProjectIds(array $projectIds): array
    {
        if (empty($projectIds)) {
            return [];
        }

        $rows = $this->select('project_id, COUNT(*) as total')
            ->whereIn('project_id', $projectIds)
            ->groupBy('project_id')
            ->findAll();

        return array_map('intval', array_column($rows, 'total', 'project_id'));
    }

    public function withRelations(int $id): ?array
    {
        return $this->filtered()->where('tasks.id', $id)->first();
    }

    /**
     * Pass $assignedTo to scope counts to a single user's own tasks
     * (used for the Employee role's personal dashboard) instead of
     * the org-wide totals every other role sees. Pass $companyId for
     * Company Admin / Super Admin's active-company dashboard scoping.
     */
    public function dashboardCounts(?int $assignedTo = null, ?int $companyId = null): array
    {
        $scoped = static function (self $model) use ($assignedTo, $companyId): self {
            if ($assignedTo !== null) {
                $model->where('assigned_to', $assignedTo);
            }
            if ($companyId !== null) {
                $model->where('company_id', $companyId);
            }

            return $model;
        };

        return [
            'active'    => $scoped($this)->whereNotIn('status', ['completed', 'cancelled'])->countAllResults(),
            'completed' => $scoped($this)->where('status', 'completed')->countAllResults(),
            'overdue'   => $scoped($this)->where('due_date <', date('Y-m-d'))->whereNotIn('status', ['completed', 'cancelled'])->countAllResults(),
        ];
    }
}
