<?php

namespace App\Modules\Task\Models;

use CodeIgniter\Model;

class ProjectModel extends Model
{
    public const STATUSES = ['active', 'completed', 'on_hold', 'cancelled'];

    protected $table         = 'projects';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['company_id', 'project_code', 'name', 'description', 'status', 'start_date', 'end_date', 'owner_id'];
    protected $useTimestamps = true;

    protected $validationRules = [
        'company_id'   => 'required|is_natural_no_zero',
        'name'         => 'required|min_length[2]|max_length[150]',
        'status'       => 'required|in_list[active,completed,on_hold,cancelled]',
        'project_code' => 'permit_empty|max_length[30]|is_unique[projects.project_code,id,{id}]',
    ];

    /**
     * getValidationRules() with project_code's {id} placeholder actually
     * resolved — same fix, same reasoning as TaskModel::validationRulesFor()
     * (CI4's {id} placeholder never resolves from a route param, so the
     * bare rule silently fails to exclude the project's own row on
     * update). Call this in update(), not getValidationRules(), and pair
     * with skipValidation(true) on the ->update() call itself.
     */
    public function validationRulesFor(?int $excludeId = null): array
    {
        $rules = $this->validationRules;
        $rules['project_code'] = 'permit_empty|max_length[30]|is_unique[projects.project_code,id,' . ($excludeId ?? 0) . ']';

        return $rules;
    }

    public function optionsForCompany(int $companyId): array
    {
        return $this->where('company_id', $companyId)->orderBy('name', 'ASC')->findAll();
    }

    /**
     * A suggested project_code for $companyId — short and company-
     * prefixed, e.g. 'SAY-P001'. Same shape/reasoning as
     * EmployeeProfileModel::nextEmployeeCode(): companies.short_code as
     * the prefix, a per-company scan-based preview (see
     * next_company_sequence_number() in company_code_helper.php), no
     * separate counter to burn/desync. Returns null when the company has
     * no short_code set yet, so the caller can fall back to leaving the
     * field blank/optional.
     */
    public function nextProjectCode(int $companyId): ?string
    {
        $shortCode = company_short_code($companyId);
        if ($shortCode === null || $shortCode === '') {
            return null;
        }

        $prefix = $shortCode . '-P';

        return $prefix . str_pad((string) next_company_sequence_number('projects', 'project_code', $companyId, $prefix), 3, '0', STR_PAD_LEFT);
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

        // array_key_exists, not empty(): 0 = "viewer has no company yet" must match zero rows, not every company.
        if (array_key_exists('company_id', $filters) && $filters['company_id'] !== null && $filters['company_id'] !== '') {
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
