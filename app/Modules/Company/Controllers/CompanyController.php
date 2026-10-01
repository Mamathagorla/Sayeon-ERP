<?php

namespace App\Modules\Company\Controllers;

use App\Controllers\BaseController;
use App\Modules\Auth\Models\UserModel;
use App\Modules\Company\Models\CompanyDirectorModel;
use App\Modules\Company\Models\BankAccountModel;
use App\Modules\Company\Models\CompanyModel;
use App\Modules\Company\Models\CountryModel;
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
    protected CountryModel $countryModel;

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
        $this->countryModel   = new CountryModel();
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
            'title'          => 'Add Company',
            'navActive'      => 'companies',
            'company'        => null,
            'owners'         => $this->userModel->listForOptions(),
            'countries'      => $this->countryModel->optionsList(),
        ]);
    }

    public function store()
    {
        if (! $this->validate($this->companyModel->validationRulesFor(), $this->companyModel->getValidationMessages())) {
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
            // EmployeeProfileModel::filtered() orders alphabetically by
            // name (right for the main Employees list's own search/
            // browse UX) — this tab specifically wants Employee ID order
            // instead, so it's re-sorted here rather than changing the
            // shared model's default for every other caller.
            'employees'  => $canViewEmployees ? $this->sortByEmployeeCode($this->employeeModel->filtered(['company_id' => $id])->findAll()) : [],
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
            'title'          => 'Edit Company',
            'navActive'      => 'companies',
            'company'        => $company,
            'owners'         => $this->userModel->listForOptions(),
            'countries'      => $this->countryModel->optionsList(),
        ]);
    }

    public function update(int $id)
    {
        $existing = $this->companyModel->find($id);

        if ($existing === null || $this->outOfScope(['admin'], $id)) {
            return redirect()->to('/companies')->with('error', 'Company not found.');
        }

        if (! $this->validate($this->companyModel->validationRulesFor($id), $this->companyModel->getValidationMessages())) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        // skipValidation: already validated above via validationRulesFor($id)
        // with the real id interpolated — the model's own $validationRules
        // still carries the plain (non-{id}-resolved) short_code rule and
        // would otherwise silently re-reject this save through Model::
        // update()'s automatic validation whenever short_code is left
        // unchanged (this exact bug already hit this exact column once
        // before this session).
        $this->companyModel->skipValidation(true)->update($id, $this->companyPayload());
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
        $countryId = $this->request->getPost('country_id') ?: null;

        return [
            'name'                => $this->request->getPost('name'),
            'short_code'          => strtoupper((string) $this->request->getPost('short_code')),
            'status'              => $this->request->getPost('status'),
            'owner_id'            => $this->request->getPost('owner_id') ?: null,
            // Legacy free-text mirror of the selected country, kept in
            // sync so anything still reading companies.country as a
            // string (reports, older code) doesn't silently go stale.
            'country'             => $countryId ? ($this->countryModel->find($countryId)['name'] ?? '') : '',
            'country_id'          => $countryId,
            // state_id is deliberately NOT set here — the State field was
            // removed from the Company form (kept in the schema/existing
            // data), so this payload must never touch that column: doing
            // so would silently null out an already-saved state on every
            // future edit even though nothing on the form ever changed it.
            'cin'                 => $this->request->getPost('cin'),
            'gst'                 => $this->request->getPost('gst'),
            'pan'                 => $this->request->getPost('pan'),
            'registered_address'  => $this->request->getPost('registered_address'),
            'incorporation_date'  => $this->request->getPost('incorporation_date') ?: null,
        ];
    }

    /**
     * Employee ID order (SAY001, SAY002, SAY003, ...) rather than the
     * alphabetical-by-name order EmployeeProfileModel::filtered() itself
     * returns — natsort()-style comparison so SAY002 sorts before
     * SAY010 (never an issue at today's 3-digit padding, but correct
     * regardless if that ever changes).
     */
    private function sortByEmployeeCode(array $employees): array
    {
        usort($employees, static fn (array $a, array $b) => strnatcmp((string) $a['employee_code'], (string) $b['employee_code']));

        return $employees;
    }
}
