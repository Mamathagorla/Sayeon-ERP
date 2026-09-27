<?php

namespace App\Modules\Purchase\Controllers;

use App\Controllers\BaseController;
use App\Modules\Company\Models\CompanyModel;
use App\Modules\Purchase\Models\VendorModel;

class VendorController extends BaseController
{
    protected VendorModel $vendorModel;
    protected CompanyModel $companyModel;

    public function __construct()
    {
        $this->vendorModel  = new VendorModel();
        $this->companyModel = new CompanyModel();
    }

    public function index()
    {
        $filters = array_filter($this->request->getGet(['company_id', 'status']) ?? []);

        $companyScope = $this->companyScopeFor(self::COMPANY_SCOPED_ROLES);
        if ($companyScope !== null) {
            $filters['company_id'] = $companyScope;
        }

        return view('App\Modules\Purchase\vendors\index', [
            'title'     => 'Vendors',
            'navActive' => 'vendors',
            'vendors'   => $this->vendorModel->filtered($filters)->findAll(),
            'companies' => $this->scopedCompanyOptions($this->companyModel->optionsList()),
            'statuses'  => VendorModel::STATUSES,
            'filters'   => $filters,
        ]);
    }

    public function create()
    {
        return view('App\Modules\Purchase\vendors\form', $this->formData(null));
    }

    public function store()
    {
        if (! $this->validate($this->vendorModel->getValidationRules(), $this->vendorModel->getValidationMessages())) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $companyId = (int) $this->request->getPost('company_id');

        if ($this->outOfScope(self::COMPANY_SCOPED_ROLES, $companyId)) {
            return redirect()->back()->withInput()->with('error', 'You can only add vendors for your own company.');
        }

        $id = $this->vendorModel->insert([
            'company_id'     => $companyId,
            'name'           => $this->request->getPost('name'),
            'contact_person' => $this->request->getPost('contact_person') ?: null,
            'email'          => $this->request->getPost('email') ?: null,
            'phone'          => $this->request->getPost('phone') ?: null,
            'address'        => $this->request->getPost('address') ?: null,
            'status'         => $this->request->getPost('status') ?: 'active',
        ]);

        $this->logActivity('vendor', 'create', $id, 'Added vendor: ' . $this->request->getPost('name'));

        return redirect()->to('/vendors')->with('success', 'Vendor added.');
    }

    public function edit(int $id)
    {
        $vendor = $this->vendorModel->find($id);

        if ($vendor === null || $this->outOfScope(self::COMPANY_SCOPED_ROLES, $vendor['company_id'])) {
            return redirect()->to('/vendors')->with('error', 'Vendor not found.');
        }

        return view('App\Modules\Purchase\vendors\form', $this->formData($vendor));
    }

    public function update(int $id)
    {
        $vendor = $this->vendorModel->find($id);

        if ($vendor === null || $this->outOfScope(self::COMPANY_SCOPED_ROLES, $vendor['company_id'])) {
            return redirect()->to('/vendors')->with('error', 'Vendor not found.');
        }

        $rules = $this->vendorModel->getValidationRules();
        unset($rules['company_id']);

        if (! $this->validate($rules, $this->vendorModel->getValidationMessages())) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $this->vendorModel->update($id, [
            'name'           => $this->request->getPost('name'),
            'contact_person' => $this->request->getPost('contact_person') ?: null,
            'email'          => $this->request->getPost('email') ?: null,
            'phone'          => $this->request->getPost('phone') ?: null,
            'address'        => $this->request->getPost('address') ?: null,
            'status'         => $this->request->getPost('status') ?: 'active',
        ]);

        $this->logActivity('vendor', 'update', $id, 'Updated vendor: ' . $this->request->getPost('name'));

        return redirect()->to('/vendors')->with('success', 'Vendor updated.');
    }

    public function delete(int $id)
    {
        $vendor = $this->vendorModel->find($id);

        if ($vendor !== null) {
            if ($this->outOfScope(self::COMPANY_SCOPED_ROLES, $vendor['company_id'])) {
                return redirect()->to('/vendors')->with('error', 'Vendor not found.');
            }

            if (db_connect()->table('purchase_orders')->where('vendor_id', $id)->countAllResults() > 0) {
                return redirect()->to('/vendors')->with('error', 'This vendor has purchase orders on record and cannot be deleted — mark it inactive instead.');
            }

            $this->vendorModel->delete($id);
            $this->logActivity('vendor', 'delete', $id, 'Deleted vendor: ' . $vendor['name']);
        }

        return redirect()->to('/vendors')->with('success', 'Vendor removed.');
    }

    private function formData(?array $vendor): array
    {
        return [
            'title'     => $vendor ? 'Edit Vendor' : 'Add Vendor',
            'navActive' => 'vendors',
            'vendor'    => $vendor,
            'companies' => $this->scopedCompanyOptions($this->companyModel->optionsList()),
            'statuses'  => VendorModel::STATUSES,
        ];
    }
}
