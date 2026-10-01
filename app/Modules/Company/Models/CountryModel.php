<?php

namespace App\Modules\Company\Models;

use CodeIgniter\Model;

/**
 * Shared reference data (see the CreateCountriesTable migration) — used by
 * both the Company form's Country dropdown and the Invoice form's Customer
 * Country dropdown.
 */
class CountryModel extends Model
{
    protected $table         = 'countries';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['name', 'iso2'];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    /**
     * India first (this ERP's home country), then the rest alphabetically
     * — same ordering the seed data was inserted in, kept explicit here
     * so it doesn't silently depend on insertion order.
     */
    public function optionsList(): array
    {
        return $this->select('id, name, iso2')
            ->orderBy("FIELD(iso2, 'IN') DESC", '', false)
            ->orderBy('name', 'ASC')
            ->findAll();
    }
}
