<?php

namespace App\Modules\Onboarding\Models;

use CodeIgniter\Model;

/**
 * Configurable "which questions does this role get" groups. company_id NULL
 * is a shared default; a company's own group with the same slug overrides
 * it for that company (see CandidateFormService::roleGroupFor()).
 */
class RoleGroupModel extends Model
{
    public const FEATURES = [
        'projects' => 'Projects section (experienced candidates)',
        'links'    => 'GitHub / portfolio links',
    ];

    protected $table         = 'onboarding_role_groups';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['company_id', 'name', 'slug', 'description', 'keywords', 'features', 'priority', 'is_active'];
    protected $useTimestamps = true;

    protected $validationRules = [
        'name'        => 'required|min_length[2]|max_length[100]',
        'slug'        => 'required|max_length[60]|regex_match[/^[a-z0-9_-]+$/]',
        'description' => 'permit_empty|max_length[2000]',
        'keywords'    => 'permit_empty|max_length[2000]',
        'priority'    => 'permit_empty|integer|greater_than_equal_to[1]|less_than_equal_to[999]',
    ];

    /** Groups a company can see: its own plus the shared defaults. */
    public function visibleTo(?int $companyId, bool $activeOnly = false): array
    {
        $builder = $this->groupStart()->where('company_id', null);
        if ($companyId !== null) {
            $builder->orWhere('company_id', $companyId);
        }
        $builder->groupEnd();
        if ($activeOnly) {
            $builder->where('is_active', 1);
        }

        return $builder->orderBy('priority', 'ASC')->orderBy('id', 'ASC')->findAll();
    }
}
