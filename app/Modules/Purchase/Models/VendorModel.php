<?php

namespace App\Modules\Purchase\Models;

use CodeIgniter\Model;

class VendorModel extends Model
{
    public const STATUSES = ['active', 'inactive'];

    protected $table         = 'vendors';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['company_id', 'name', 'contact_person', 'email', 'phone', 'address', 'status'];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'company_id'     => 'required|integer',
        'name'           => 'required|min_length[2]|max_length[150]',
        // Same person-name convention used across the app (Onboarding's
        // candidate_name, Employee's emergency_contact_name) — a name,
        // not an arbitrary free-text value.
        'contact_person' => 'permit_empty|min_length[2]|max_length[150]|regex_match[/^[\p{L}\s.\'-]+$/u]',
        'email'          => 'permit_empty|valid_email',
        'phone'          => 'permit_empty|regex_match[/^[0-9+\-\s]{6,20}$/]',
        'status'         => 'permit_empty|in_list[active,inactive]',
    ];

    protected $validationMessages = [
        'contact_person' => ['regex_match' => 'Contact person may only contain letters, spaces, apostrophes, hyphens and periods.'],
        'phone'          => ['regex_match' => 'Enter a valid phone number.'],
    ];

    public function filtered(array $filters = [])
    {
        $builder = $this->select('vendors.*, companies.name as company_name')
            ->join('companies', 'companies.id = vendors.company_id');

        if (array_key_exists('company_id', $filters)) {
            $builder->where('vendors.company_id', $filters['company_id']);
        }
        if (! empty($filters['status'])) {
            $builder->where('vendors.status', $filters['status']);
        }

        return $builder->orderBy('vendors.name', 'ASC');
    }

    public function optionsForCompany(int $companyId): array
    {
        return $this->where('company_id', $companyId)->where('status', 'active')->orderBy('name', 'ASC')->findAll();
    }
}
