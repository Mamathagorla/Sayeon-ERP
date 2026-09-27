<?php

namespace App\Modules\Task\Models;

use CodeIgniter\Model;

class ProjectModel extends Model
{
    public const STATUSES = ['active', 'completed', 'on_hold', 'cancelled'];

    protected $table         = 'projects';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['company_id', 'name', 'description', 'status', 'start_date', 'end_date', 'owner_id'];
    protected $useTimestamps = true;

    protected $validationRules = [
        'company_id' => 'required|is_natural_no_zero',
        'name'       => 'required|min_length[2]|max_length[150]',
        'status'     => 'required|in_list[active,completed,on_hold,cancelled]',
    ];

    public function optionsForCompany(int $companyId): array
    {
        return $this->where('company_id', $companyId)->orderBy('name', 'ASC')->findAll();
    }

    /**
     * Projects with company + owner names attached, for the list view.
     * Pass $filters (company_id, status) to narrow the results — same
     * filter-form pattern used by Tasks/Compliance.
     */
    public function filtered(array $filters = [])
    {
        $builder = $this->select('projects.*, companies.name as company_name, users.name as owner_name')
            ->join('companies', 'companies.id = projects.company_id', 'left')
            ->join('users', 'users.id = projects.owner_id', 'left');

        if (! empty($filters['company_id'])) {
            $builder->where('projects.company_id', $filters['company_id']);
        }
        if (! empty($filters['status'])) {
            $builder->where('projects.status', $filters['status']);
        }

        return $builder->orderBy('projects.name', 'ASC');
    }

    /**
     * Whether this project has any tasks linked to it — used to block
     * deletion of projects still in use.
     */
    public function isInUse(int $id): bool
    {
        // tasks are soft-deleted (deleted_at), so a deleted task must not
        // count as still "using" the project — otherwise the project can
        // never be cleaned up once its last task is removed.
        return db_connect()->table('tasks')
            ->where('project_id', $id)
            ->where('deleted_at', null)
            ->countAllResults() > 0;
    }
}
