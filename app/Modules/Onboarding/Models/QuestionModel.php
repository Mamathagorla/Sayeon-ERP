<?php

namespace App\Modules\Onboarding\Models;

use CodeIgniter\Model;

class QuestionModel extends Model
{
    public const TYPES = [
        'text'     => 'Short text',
        'textarea' => 'Long text',
        'select'   => 'Choose one (dropdown)',
        'tags'     => 'List of items (comma separated)',
        'url'      => 'Web link',
        'number'   => 'Number',
        'yes_no'   => 'Yes / No',
    ];
    public const APPLIES_TO = ['all' => 'All candidates', 'fresher' => 'Freshers only', 'experienced' => 'Experienced only'];

    protected $table         = 'onboarding_questions';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['role_group_id', 'question_key', 'label', 'field_type', 'options', 'help_text', 'is_required', 'applies_to', 'sort_order', 'is_active'];
    protected $useTimestamps = true;

    protected $validationRules = [
        'label'      => 'required|min_length[3]|max_length[255]',
        'field_type' => 'required|in_list[text,textarea,select,tags,url,number,yes_no]',
        'options'    => 'permit_empty|max_length[2000]',
        'help_text'  => 'permit_empty|max_length[255]',
        'applies_to' => 'required|in_list[all,fresher,experienced]',
        'sort_order' => 'permit_empty|integer|greater_than_equal_to[0]|less_than_equal_to[9999]',
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
