<?php

namespace App\Modules\Company\Controllers;

use App\Controllers\BaseController;
use App\Modules\Auth\Models\UserModel;
use App\Modules\Company\Models\CompanyDirectorModel;
use App\Modules\Company\Models\BankAccountModel;
use App\Modules\Company\Models\CompanyModel;
use App\Modules\Compliance\Models\ComplianceItemModel;
use App\Modules\Document\Models\DocumentModel;
use App\Modules\HR\Models\EmployeeProfileModel;
use App\Modules\Website\Models\WebsiteModel;

class CompanyController extends BaseController
{
    protected CompanyModel $companyModel;
    protected CompanyDirectorModel $directorModel;
    protected UserModel $userModel;
    protected ComplianceItemModel $complianceModel;
    protected EmployeeProfileModel $employeeModel;
    protected BankAccountModel $bankAccountModel;
    protected DocumentModel $documentModel;
    protected WebsiteModel $websiteModel;

    public function __construct()
    {
        $this->companyModel   = new CompanyModel();
        $this->directorModel  = new CompanyDirectorModel();
        $this->userModel      = new UserModel();
        $this->complianceModel = new ComplianceItemModel();
        $this->employeeModel  = new EmployeeProfileModel();
        $this->bankAccountModel = new BankAccountModel();
        $this->documentModel  = new DocumentModel();
        $this->websiteModel   = new WebsiteModel();
    }

    /**
     * Company Admin, like Super Admin, browses via the topbar switcher
     * (main.php) rather than this list — with "All Companies" selected
     * (their default) they see the same list Super Admin does; once
     * they've switched to one company, this redirects straight to its
     * show page instead of a one-row list.
     */
    public function index()
    {
        $companyScope = $this->companyScopeFor(['admin']);

        if ($companyScope !== null) {
            if ($companyScope === 0) {
                return redirect()->to('/')->with('error', 'Your account isn\'t linked to a company yet — contact your administrator.');
            }

            return redirect()->to('/companies/' . $companyScope);
        }

        return view('App\Modules\Company\index', [
            'title'     => 'Companies',
            'navActive' => 'companies',
            'companies' => $this->companyModel->listWithOwner(),
        ]);
    }

    /**
     * Super Admin's "which company am I browsing" switcher (topbar
     * dropdown, main.php). Company Admin is deliberately excluded —
     * they're pinned to their own company (see
     * AuthController::establishActiveCompany()) and must not be able to
     * browse into another company's data by posting here directly, even
     * though the switcher UI is already hidden from them.
     */
    public function switchActive()
    {
        if (session('roleSlug') !== 'super_admin') {
            return redirect()->back()->with('error', 'You can only view your own company.');
        }

        $companyId = $this->request->getPost('company_id');

        if ($companyId === null || $companyId === '') {
            session()->set('active_company_id', self::ACTIVE_COMPANY_ALL);

            return redirect()->back()->with('success', 'Now viewing all companies.');
        }

        $company = $this->companyModel->find((int) $companyId);

        if ($company === null) {
            return redirect()->back()->with('error', 'Company not found.');
        }

        session()->set('active_company_id', (int) $companyId);

        return redirect()->back()->with('success', 'Now viewing ' . $company['name'] . '.');
    }

    public function create()
    {
        return view('App\Modules\Company\form', [
            'title'     => 'Add Company',
            'navActive' => 'companies',
            'company'   => null,
            'owners'    => $this->userModel->listForOptions(),
        ]);
    }

    public function store()
    {
        if (! $this->validate($this->companyModel->getValidationRules(), $this->companyModel->getValidationMessages())) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $id = $this->companyModel->insert($this->companyPayload());
        $this->logActivity('company', 'create', $id, 'Created company ' . $this->request->getPost('name'));

        return redirect()->to('/companies')->with('success', 'Company created successfully.');
    }

    public function show(int $id)
    {
        $company = $this->companyModel->withOwner($id);

        if ($company === null) {
            return redirect()->to('/companies')->with('error', 'Company not found.');
        }

        $companyScope = $this->companyScopeFor(['admin']);
        if ($companyScope !== null && $companyScope !== $id) {
            return redirect()->to('/companies')->with('error', 'You can only view your own company.');
        }

        $canViewCompliance = can('compliance.view');
        $canViewEmployees  = can('employee.view');
        $canViewBankAccounts = can('bank_account.view');
        $canViewDocuments    = can('document.view');
        $canViewWebsites     = can('website.view');

        if ($canViewCompliance) {
            $this->complianceModel->refreshOverdueStatuses();
        }

        return view('App\Modules\Company\show', [
            'title'      => $company['name'],
            'navActive'  => 'companies',
            'company'    => $company,
            'directors'  => $this->directorModel->forCompany($id),
            'compliance' => $canViewCompliance ? $this->complianceModel->filtered(['company_id' => $id])->findAll() : [],
            'canViewEmployees' => $canViewEmployees,
            'employees'  => $canViewEmployees ? $this->employeeModel->filtered(['company_id' => $id])->findAll() : [],
            'canViewBankAccounts' => $canViewBankAccounts,
            'bankAccounts' => $canViewBankAccounts ? $this->bankAccountModel->forCompany($id) : [],
            'canViewDocuments' => $canViewDocuments,
            'documents'  => $canViewDocuments ? $this->documentModel->forCompany($id) : [],
            'canViewWebsites' => $canViewWebsites,
            'websites'   => $canViewWebsites ? $this->websiteModel->filtered(['company_id' => $id])->findAll() : [],
        ]);
    }

    public function edit(int $id)
    {
        $company = $this->companyModel->find($id);

        if ($company === null || $this->outOfScope(['admin'], $id)) {
            return redirect()->to('/companies')->with('error', 'Company not found.');
        }

        return view('App\Modules\Company\form', [
            'title'     => 'Edit Company',
            'navActive' => 'companies',
            'company'   => $company,
            'owners'    => $this->userModel->listForOptions(),
        ]);
    }

    public function update(int $id)
    {
        if ($this->companyModel->find($id) === null || $this->outOfScope(['admin'], $id)) {
            return redirect()->to('/companies')->with('error', 'Company not found.');
        }

        if (! $this->validate($this->companyModel->getValidationRules(), $this->companyModel->getValidationMessages())) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $this->companyModel->update($id, $this->companyPayload());
        $this->logActivity('company', 'update', $id, 'Updated company #' . $id);

        return redirect()->to('/companies/' . $id)->with('success', 'Company updated successfully.');
    }

    /**
     * company.delete is currently Super-Admin-only (see
     * RolePermissionSeeder — deliberately not granted to Company Admin,
     * unlike company.edit), so this scope check is defense-in-depth
     * for if that ever changes, not a fix for a reachable gap today.
     */
    public function delete(int $id)
    {
        if ($this->companyModel->find($id) === null || $this->outOfScope(['admin'], $id)) {
            return redirect()->to('/companies')->with('error', 'Company not found.');
        }

        $this->companyModel->delete($id);
        $this->logActivity('company', 'delete', $id, 'Deactivated company #' . $id);

        return redirect()->to('/companies')->with('success', 'Company removed.');
    }

    private function companyPayload(): array
    {
        return [
            'name'                => $this->request->getPost('name'),
            'status'              => $this->request->getPost('status'),
            'owner_id'            => $this->request->getPost('owner_id') ?: null,
            'country'             => $this->request->getPost('country'),
            'cin'                 => $this->request->getPost('cin'),
            'gst'                 => $this->request->getPost('gst'),
            'pan'                 => $this->request->getPost('pan'),
            'registered_address'  => $this->request->getPost('registered_address'),
            'incorporation_date'  => $this->request->getPost('incorporation_date') ?: null,
        ];
    }
}
