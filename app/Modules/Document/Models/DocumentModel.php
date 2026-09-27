<?php

namespace App\Modules\Document\Models;

use CodeIgniter\Model;

class DocumentModel extends Model
{
    public const CATEGORIES = ['legal', 'finance', 'hr', 'it', 'marketing', 'compliance', 'general'];

    protected $table         = 'documents';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = [
        'company_id', 'category', 'document_type', 'title', 'employee_user_id',
        'onboarding_record_id', 'policy_id', 'file_path', 'original_name', 'file_size',
        'expiry_date', 'uploaded_by',
    ];
    protected $useTimestamps = false;

    protected $validationRules = [
        'company_id' => 'required|integer',
        'category'   => 'required|in_list[legal,finance,hr,it,marketing,compliance,general]',
        'title'      => 'required|min_length[2]|max_length[150]',
    ];

    protected $beforeInsert = ['stampCreatedAt'];

    protected function stampCreatedAt(array $data): array
    {
        $data['data']['created_at'] = date('Y-m-d H:i:s');

        return $data;
    }

    public function filtered(array $filters = [])
    {
        $builder = $this->select('documents.*, companies.name as company_name, users.name as uploaded_by_name, employee.name as employee_name')
            ->join('companies', 'companies.id = documents.company_id')
            ->join('users', 'users.id = documents.uploaded_by', 'left')
            ->join('users as employee', 'employee.id = documents.employee_user_id', 'left');

        // array_key_exists (not empty()) — 0 means "Company Admin has no
        // employee_profiles company assigned yet" and must match zero
        // rows, not fall through to showing every company's documents.
        if (array_key_exists('company_id', $filters)) {
            $builder->where('documents.company_id', $filters['company_id']);
        }
        if (! empty($filters['category'])) {
            is_array($filters['category'])
                ? $builder->whereIn('documents.category', $filters['category'])
                : $builder->where('documents.category', $filters['category']);
        }
        if (! empty($filters['employee_user_id'])) {
            $builder->where('documents.employee_user_id', $filters['employee_user_id']);
        }
        if (! empty($filters['onboarding_record_id'])) {
            $builder->where('documents.onboarding_record_id', $filters['onboarding_record_id']);
        }
        if (! empty($filters['policy_id'])) {
            $builder->where('documents.policy_id', $filters['policy_id']);
        }

        return $builder->orderBy('documents.created_at', 'DESC');
    }

    public function forCompany(int $companyId): array
    {
        return $this->filtered(['company_id' => $companyId])->findAll();
    }
}
