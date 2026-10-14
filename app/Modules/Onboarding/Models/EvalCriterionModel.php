<?php

namespace App\Modules\Onboarding\Models;

use CodeIgniter\Model;

class EvalCriterionModel extends Model
{
    protected $table         = 'onboarding_eval_criteria';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['role_group_id', 'label', 'description', 'sort_order', 'is_active'];
    protected $useTimestamps = true;

    protected $validationRules = [
        'label'       => 'required|min_length[3]|max_length[150]',
        'description' => 'permit_empty|max_length[255]',
        'sort_order'  => 'permit_empty|integer|greater_than_equal_to[0]|less_than_equal_to[9999]',
    ];

    public function forGroup(int $groupId, bool $activeOnly = false): array
    {
        $builder = $this->where('role_group_id', $groupId);
        if ($activeOnly) {
            $builder->where('is_active', 1);
        }

        return $builder->orderBy('sort_order', 'ASC')->orderBy('id', 'ASC')->findAll();
    }
}
