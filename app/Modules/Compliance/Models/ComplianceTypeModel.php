<?php

namespace App\Modules\Compliance\Models;

use CodeIgniter\Model;

class ComplianceTypeModel extends Model
{
    protected $table         = 'compliance_types';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['name', 'slug'];
    protected $useTimestamps = false;

    public function optionsList(): array
    {
        return $this->orderBy('name', 'ASC')->findAll();
    }
}
