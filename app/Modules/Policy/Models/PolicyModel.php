<?php

namespace App\Modules\Policy\Models;

use CodeIgniter\Model;

class PolicyModel extends Model
{
    public const TYPES    = ['policy', 'manual'];
    public const STATUSES = ['draft', 'published', 'archived'];

    protected $table         = 'policies';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = [
        'company_id', 'title', 'type', 'owner_id', 'version',
        'last_review_date', 'retention_years', 'status', 'document_id', 'created_by',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'company_id'       => 'required|integer',
        'title'            => 'required|min_length[2]|max_length[150]',
        'type'             => 'required|in_list[policy,manual]',
        'version'          => 'permit_empty|max_length[20]',
        'last_review_date' => 'permit_empty|valid_date',
        'retention_years'  => 'permit_empty|integer|greater_than_equal_to[0]|less_than_equal_to[100]',
        'status'           => 'permit_empty|in_list[draft,published,archived]',
    ];

    public function filtered(array $filters = [])
    {
        $builder = $this->select('policies.*, companies.name as company_name, users.name as owner_name,
                documents.file_path as file_path, documents.original_name as file_name')
            ->join('companies', 'companies.id = policies.company_id')
            ->join('users', 'users.id = policies.owner_id', 'left')
            ->join('documents', 'documents.id = policies.document_id', 'left');

        // array_key_exists (not empty()) — same convention as every
        // other module's filtered(): 0 must match zero rows, not fall
        // through to "no restriction".
        if (array_key_exists('company_id', $filters)) {
            $builder->where('policies.company_id', $filters['company_id']);
        }
        if (! empty($filters['type'])) {
            $builder->where('policies.type', $filters['type']);
        }
        if (! empty($filters['status'])) {
            $builder->where('policies.status', $filters['status']);
        }

        return $builder->orderBy('policies.updated_at', 'DESC');
    }

    public function withRelations(int $id): ?array
    {
        return $this->filtered()->where('policies.id', $id)->first();
    }
}
