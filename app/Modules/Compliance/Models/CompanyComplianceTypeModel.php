<?php

namespace App\Modules\Compliance\Models;

use CodeIgniter\Model;

class CompanyComplianceTypeModel extends Model
{
    protected $table         = 'company_compliance_types';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['company_id', 'compliance_type_id'];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = '';

    public function applicableIdsFor(int $companyId): array
    {
        return array_map('intval', array_column(
            $this->where('company_id', $companyId)->findAll(),
            'compliance_type_id'
        ));
    }

    /**
     * Every company_id that has configured at least one applicable
     * type, grouped to its list of compliance_type_ids — one query for
     * every company at once (used to build the Compliance form's
     * per-company type filter in JS) rather than one query per company.
     */
    public function applicableIdsByCompany(): array
    {
        $rows = $this->findAll();

        $grouped = [];
        foreach ($rows as $row) {
            $grouped[(int) $row['company_id']][] = (int) $row['compliance_type_id'];
        }

        return $grouped;
    }

    /**
     * Replaces $companyId's whole applicable-types set with $typeIds in
     * one transaction — simplest correct semantics for a checkbox-list
     * settings form (unchecked = removed, newly checked = added).
     */
    public function setApplicable(int $companyId, array $typeIds): void
    {
        $typeIds = array_unique(array_map('intval', $typeIds));

        $this->db->transStart();

        $this->where('company_id', $companyId)->delete();

        if ($typeIds !== []) {
            $this->insertBatch(array_map(
                static fn (int $typeId) => ['company_id' => $companyId, 'compliance_type_id' => $typeId],
                $typeIds
            ));
        }

        $this->db->transComplete();
    }
}
