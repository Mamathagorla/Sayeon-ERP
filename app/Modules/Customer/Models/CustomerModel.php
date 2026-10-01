<?php

namespace App\Modules\Customer\Models;

use CodeIgniter\Model;

class CustomerModel extends Model
{
    public const STATUSES = ['active', 'inactive'];

    // Same 15-character GSTIN format CompanyModel/InvoiceModel already
    // validate their own gst/customer_gstin fields against.
    private const GSTIN_PATTERN = '/^[0-9]{2}[A-Za-z]{5}[0-9]{4}[A-Za-z]{1}[1-9A-Za-z]{1}Z[0-9A-Za-z]{1}$/';

    // India's PAN format — 5 letters, 4 digits, 1 letter (the same
    // structure GSTIN's own middle segment embeds, since a GSTIN is
    // built around a PAN).
    private const PAN_PATTERN = '/^[A-Za-z]{5}[0-9]{4}[A-Za-z]{1}$/';

    protected $table         = 'customers';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = [
        'company_id', 'name', 'contact_person', 'email', 'phone', 'gstin', 'pan', 'address',
        'country_id', 'state_id', 'city', 'status',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'company_id'     => 'required|integer',
        'name'           => 'required|min_length[2]|max_length[150]',
        'contact_person' => 'permit_empty|max_length[150]',
        'email'          => 'permit_empty|valid_email|max_length[190]',
        'phone'          => 'permit_empty|regex_match[/^[0-9+\-\s]{6,20}$/]',
        'gstin'          => 'permit_empty|regex_match[' . self::GSTIN_PATTERN . ']',
        'pan'            => 'permit_empty|regex_match[' . self::PAN_PATTERN . ']',
        'country_id'     => 'permit_empty|integer',
        'state_id'       => 'permit_empty|integer',
        'city'           => 'permit_empty|max_length[100]',
        'status'         => 'permit_empty|in_list[active,inactive]',
    ];

    protected $validationMessages = [
        'gstin' => ['regex_match' => 'Enter a valid 15-character GSTIN (e.g. 27ABCDE1234F1Z5).'],
        'pan'   => ['regex_match' => 'Enter a valid 10-character PAN (e.g. ABCDE1234F).'],
        'phone' => ['regex_match' => 'Enter a valid phone number.'],
    ];

    /**
     * List with joined company/country/state names — the Customer index
     * table and (later) any "pick an existing customer" dropdown on the
     * Invoice form.
     */
    public function filtered(array $filters = [])
    {
        $builder = $this->select('customers.*, companies.name as company_name, countries.name as country_name, states.name as state_name, states.gst_state_code as state_gst_code')
            ->join('companies', 'companies.id = customers.company_id')
            ->join('countries', 'countries.id = customers.country_id', 'left')
            ->join('states', 'states.id = customers.state_id', 'left');

        // array_key_exists (not empty()) — 0 means "Company Admin has no
        // company assigned yet" and must match zero rows, same
        // convention as VendorModel::filtered()/EmployeeProfileModel::filtered().
        if (array_key_exists('company_id', $filters)) {
            $builder->where('customers.company_id', $filters['company_id']);
        }
        if (! empty($filters['status'])) {
            $builder->where('customers.status', $filters['status']);
        }
        if (! empty($filters['q'])) {
            $builder->groupStart()
                ->like('customers.name', $filters['q'])
                ->orLike('customers.email', $filters['q'])
                ->orLike('customers.gstin', $filters['q'])
                ->groupEnd();
        }

        return $builder->orderBy('customers.name', 'ASC');
    }

    public function withRelations(int $id): ?array
    {
        return $this->filtered()->where('customers.id', $id)->first();
    }

    /**
     * Active customers for one company — for a future "pick an existing
     * customer" convenience on the Invoice form, same shape as
     * VendorModel::optionsForCompany().
     */
    public function optionsForCompany(int $companyId): array
    {
        return $this->where('company_id', $companyId)->where('status', 'active')->orderBy('name', 'ASC')->findAll();
    }
}
