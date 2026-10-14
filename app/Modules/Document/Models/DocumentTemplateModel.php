<?php

namespace App\Modules\Document\Models;

use CodeIgniter\Model;

class DocumentTemplateModel extends Model
{
    protected $table         = 'document_templates';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = [
        'company_id', 'document_type', 'workflow', 'template_name', 'version', 'status', 'file_type', 'field_map', 'field_review', 'source_template_id', 'previewed_at',
        'file_path', 'original_name', 'file_size', 'notes', 'created_by',
    ];
    protected $useTimestamps = true;

    public function filtered(array $filters = [])
    {
        $builder = $this->select('document_templates.*, companies.name as company_name,
                src.version as source_version, src_co.name as source_company_name')
            ->join('companies', 'companies.id = document_templates.company_id')
            ->join('document_templates as src', 'src.id = document_templates.source_template_id', 'left')
            ->join('companies as src_co', 'src_co.id = src.company_id', 'left');

        // array_key_exists, not empty(): 0 = "no company assigned" and
        // must match zero rows rather than every company.
        if (array_key_exists('company_id', $filters)) {
            $builder->where('document_templates.company_id', $filters['company_id']);
        }
        if (! empty($filters['document_type'])) {
            $builder->where('document_templates.document_type', $filters['document_type']);
        }
        if (! empty($filters['status'])) {
            $builder->where('document_templates.status', $filters['status']);
        }

        return $builder->orderBy('companies.name')->orderBy('document_templates.document_type')->orderBy('document_templates.version', 'DESC');
    }

    /**
     * The one active template for exactly this company + type. Strictly
     * company-matched — there is deliberately no fallback to another
     * company's (or a "global") template.
     */
    public function activeFor(int $companyId, string $type): ?array
    {
        return $this->where(['company_id' => $companyId, 'document_type' => $type, 'status' => 'active'])->first();
    }

    public function nextVersion(int $companyId, string $type): int
    {
        $max = $this->selectMax('version')->where(['company_id' => $companyId, 'document_type' => $type])->first();

        return ((int) ($max['version'] ?? 0)) + 1;
    }

    /** Makes $id the only active template for its company + type. */
    public function activate(int $id): void
    {
        $tpl = $this->find($id);
        if ($tpl === null) {
            return;
        }

        $this->db->transStart();
        // Only the currently active version is retired; drafts keep their draft state.
        $this->builder()
            ->where(['company_id' => $tpl['company_id'], 'document_type' => $tpl['document_type'], 'status' => 'active'])
            ->update(['status' => 'inactive', 'updated_at' => date('Y-m-d H:i:s')]);
        $this->update($id, ['status' => 'active']);
        $this->db->transComplete();
    }

    public function usageCount(int $id): int
    {
        return $this->db->table('generated_documents')->where('template_id', $id)->countAllResults();
    }
}
