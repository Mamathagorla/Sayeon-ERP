<?php

namespace App\Modules\Customer\Controllers;

use App\Controllers\BaseController;
use App\Modules\Company\Models\CompanyModel;
use App\Modules\Company\Models\CountryModel;
use App\Modules\Company\Models\StateModel;
use App\Modules\Customer\Models\CustomerModel;

class CustomerController extends BaseController
{
    protected CustomerModel $customerModel;
    protected CompanyModel $companyModel;
    protected CountryModel $countryModel;
    protected StateModel $stateModel;

    public function __construct()
    {
        $this->customerModel = new CustomerModel();
        $this->companyModel  = new CompanyModel();
        $this->countryModel  = new CountryModel();
        $this->stateModel    = new StateModel();
    }

    public function index()
    {
        $filters = array_filter($this->request->getGet(['company_id', 'status', 'q']) ?? []);

        $companyScope = $this->companyScopeFor(self::COMPANY_SCOPED_ROLES);
        if ($companyScope !== null) {
            $filters['company_id'] = $companyScope;
        }

        return view('App\Modules\Customer\index', [
            'title'     => 'Customers',
            'navActive' => 'customers',
            'customers' => $this->customerModel->filtered($filters)->findAll(),
            'companies' => $this->scopedCompanyOptions($this->companyModel->optionsList()),
            'statuses'  => CustomerModel::STATUSES,
            'filters'   => $filters,
        ]);
    }

    public function show(int $id)
    {
        $customer = $this->customerModel->withRelations($id);

        if ($customer === null || $this->outOfScope(self::COMPANY_SCOPED_ROLES, $customer['company_id'])) {
            return redirect()->to('/customers')->with('error', 'Customer not found.');
        }

        return view('App\Modules\Customer\show', [
            'title'     => $customer['name'],
            'navActive' => 'customers',
            'customer'  => $customer,
        ]);
    }

    public function create()
    {
        return view('App\Modules\Customer\form', $this->formData(null));
    }

    public function store()
    {
        if (! $this->validate($this->customerModel->getValidationRules(), $this->customerModel->getValidationMessages())) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $companyId = (int) $this->request->getPost('company_id');

        if ($this->outOfScope(self::COMPANY_SCOPED_ROLES, $companyId)) {
            return redirect()->back()->withInput()->with('error', 'You can only add customers for your own company.');
        }

        if ($stateError = $this->stateError()) {
            return redirect()->back()->withInput()->with('error', $stateError);
        }

        if ($gstinError = $this->gstinPrefixError()) {
            return redirect()->back()->withInput()->with('error', $gstinError);
        }

        $id = $this->customerModel->insert($this->payload($companyId));

        $this->logActivity('customer', 'create', $id, 'Added customer: ' . $this->request->getPost('name'));

        return redirect()->to('/customers')->with('success', 'Customer added.');
    }

    public function edit(int $id)
    {
        // withRelations() (not a plain find()) — form.php shows the
        // customer's company name as a disabled field in edit mode, same
        // as VendorController::edit() intends, and that needs the join.
        $customer = $this->customerModel->withRelations($id);

        if ($customer === null || $this->outOfScope(self::COMPANY_SCOPED_ROLES, $customer['company_id'])) {
            return redirect()->to('/customers')->with('error', 'Customer not found.');
        }

        return view('App\Modules\Customer\form', $this->formData($customer));
    }

    public function update(int $id)
    {
        $customer = $this->customerModel->find($id);

        if ($customer === null || $this->outOfScope(self::COMPANY_SCOPED_ROLES, $customer['company_id'])) {
            return redirect()->to('/customers')->with('error', 'Customer not found.');
        }

        $rules = $this->customerModel->getValidationRules();
        unset($rules['company_id']);

        if (! $this->validate($rules, $this->customerModel->getValidationMessages())) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        if ($stateError = $this->stateError()) {
            return redirect()->back()->withInput()->with('error', $stateError);
        }

        if ($gstinError = $this->gstinPrefixError()) {
            return redirect()->back()->withInput()->with('error', $gstinError);
        }

        $this->customerModel->update($id, $this->payload((int) $customer['company_id']));

        $this->logActivity('customer', 'update', $id, 'Updated customer: ' . $this->request->getPost('name'));

        return redirect()->to('/customers')->with('success', 'Customer updated.');
    }

    public function delete(int $id)
    {
        $customer = $this->customerModel->find($id);

        if ($customer !== null) {
            if ($this->outOfScope(self::COMPANY_SCOPED_ROLES, $customer['company_id'])) {
                return redirect()->to('/customers')->with('error', 'Customer not found.');
            }

            $this->customerModel->delete($id);
            $this->logActivity('customer', 'delete', $id, 'Deleted customer: ' . $customer['name']);
        }

        return redirect()->to('/customers')->with('success', 'Customer removed.');
    }

    private function payload(int $companyId): array
    {
        return [
            'company_id'     => $companyId,
            'name'           => $this->request->getPost('name'),
            'contact_person' => $this->request->getPost('contact_person') ?: null,
            'email'          => $this->request->getPost('email') ?: null,
            'phone'          => $this->request->getPost('phone') ?: null,
            'gstin'          => $this->request->getPost('gstin') ? strtoupper((string) $this->request->getPost('gstin')) : null,
            'pan'            => $this->request->getPost('pan') ? strtoupper((string) $this->request->getPost('pan')) : null,
            'address'        => $this->request->getPost('address') ?: null,
            'country_id'     => $this->request->getPost('country_id') ?: null,
            'state_id'       => $this->request->getPost('state_id') ?: null,
            'city'           => $this->request->getPost('city') ?: null,
            'status'         => $this->request->getPost('status') ?: 'active',
        ];
    }

    /**
     * Server-side re-check of "the final GSTIN must be validated against
     * the selected state's prefix" — the form's JS already locks the
     * prefix box to the selected state's code, but this is the
     * authoritative check (never trusts what the client posted). Returns
     * null when there's nothing to reject: no GSTIN entered, or the
     * selected state has no GST state code at all (not an Indian state),
     * same "nothing to check" case gstin_matches_state_prefix() itself
     * defines.
     */
    private function gstinPrefixError(): ?string
    {
        $gstin = trim((string) $this->request->getPost('gstin'));
        $stateId = $this->request->getPost('state_id') ?: null;

        if ($gstin === '' || $stateId === null) {
            return null;
        }

        $state = $this->stateModel->find((int) $stateId);
        $expectedPrefix = $state['gst_state_code'] ?? null;

        if (! gstin_matches_state_prefix($gstin, $expectedPrefix)) {
            return "GSTIN must start with {$expectedPrefix} for the selected state.";
        }

        return null;
    }

    /**
     * A customer's state must belong to the customer's country, the same rule
     * as CompanyController::stateError(). A blank state is allowed. The GSTIN
     * prefix check (gstinPrefixError()) still runs after this.
     */
    private function stateError(): ?string
    {
        $stateId = $this->request->getPost('state_id') ?: null;

        if ($stateId === null) {
            return null;
        }

        $state = $this->stateModel->find((int) $stateId);

        return $state !== null && (int) $state['country_id'] === (int) $this->request->getPost('country_id')
            ? null
            : 'Choose a state from the selected country.';
    }

    private function formData(?array $customer): array
    {
        return [
            'title'           => $customer ? 'Edit Customer' : 'Add Customer',
            'navActive'       => 'customers',
            'customer'        => $customer,
            'companies'       => $this->scopedCompanyOptions($this->companyModel->optionsList()),
            'statuses'        => CustomerModel::STATUSES,
            'countries'       => $this->countryModel->optionsList(),
            'statesByCountry' => $this->stateModel->allGroupedByCountry(),
        ];
    }
}
