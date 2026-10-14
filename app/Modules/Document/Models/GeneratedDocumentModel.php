<?php

namespace App\Modules\Document\Models;

use CodeIgniter\Model;

class GeneratedDocumentModel extends Model
{
    protected $table         = 'generated_documents';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = [
        'company_id', 'template_id', 'template_version', 'document_type', 'file_type', 'record_id', 'data_hash', 'data_snapshot',
        'title', 'file_path', 'file_size', 'generated_by', 'emailed_to', 'emailed_at',
    ];
    protected $useTimestamps = true;
    protected $updatedField  = '';

    public function forRecord(int $companyId, string $type, int $recordId): array
    {
        return $this->where(['company_id' => $companyId, 'document_type' => $type, 'record_id' => $recordId])
            ->orderBy('id', 'DESC')
            ->findAll();
    }

    public function recent(?int $companyScope, int $limit = 15): array
    {
        $builder = $this->select('generated_documents.*, companies.name as company_name, users.name as generated_by_name')
            ->join('companies', 'companies.id = generated_documents.company_id')
            ->join('users', 'users.id = generated_documents.generated_by', 'left');

        // Same 0-matches-nothing convention as the other scoped queries.
        if ($companyScope !== null) {
            $builder->where('generated_documents.company_id', $companyScope);
        }

        return $builder->orderBy('generated_documents.id', 'DESC')->findAll($limit);
    }
}
