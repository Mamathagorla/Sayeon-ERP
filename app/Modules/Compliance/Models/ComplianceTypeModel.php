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

    /**
     * Finds-or-creates by slug — "Selecting/creating the applicable
     * compliance type" from the Compliance form's inline "+ Add new
     * type" control. Reuses an existing type with the same slug instead
     * of ever inserting a second row for what's really the same
     * requirement spelled differently (e.g. "GST Filing" vs "gst filing").
     */
    public function findOrCreate(string $name): array
    {
        $name = trim($name);
        $slug = url_title($name, '-', true);

        $existing = $this->where('slug', $slug)->first();
        if ($existing !== null) {
            return $existing;
        }

        $id = $this->insert(['name' => $name, 'slug' => $slug], true);

        return $this->find($id);
    }
}
