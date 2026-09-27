<?php

namespace App\Modules\Website\Controllers;

use App\Controllers\BaseController;
use App\Modules\Company\Models\CompanyModel;
use App\Modules\Website\Models\WebsiteModel;

class WebsiteController extends BaseController
{
    protected WebsiteModel $websiteModel;
    protected CompanyModel $companyModel;

    public function __construct()
    {
        $this->websiteModel = new WebsiteModel();
        $this->companyModel = new CompanyModel();
    }

    public function index()
    {
        $filters = array_filter($this->request->getGet(['company_id', 'status']) ?? []);

        $companyScope = $this->companyScopeFor(self::COMPANY_SCOPED_ROLES);
        if ($companyScope !== null) {
            $filters['company_id'] = $companyScope;
        }

        return view('App\Modules\Website\index', [
            'title'     => 'Websites & Hosting',
            'navActive' => 'websites',
            'websites'  => $this->websiteModel->filtered($filters)->findAll(),
            'companies' => $this->scopedCompanyOptions($this->companyModel->optionsList()),
            'statuses'  => WebsiteModel::STATUSES,
            'filters'   => $filters,
        ]);
    }

    public function create()
    {
        return view('App\Modules\Website\form', array_merge(
            $this->formData(null),
            ['defaultCompanyId' => $this->request->getGet('company_id')]
        ));
    }

    public function store()
    {
        if (! $this->validate($this->websiteModel->getValidationRules())) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        if ($this->outOfScope(self::COMPANY_SCOPED_ROLES, (int) $this->request->getPost('company_id'))) {
            return redirect()->back()->withInput()->with('error', 'You can only add websites for your own company.');
        }

        $id = $this->websiteModel->insert($this->payload(true));
        $this->logActivity('website', 'create', $id, 'Added website: ' . $this->request->getPost('domain'));

        return redirect()->to('/websites/' . $id)->with('success', 'Website added.');
    }

    public function show(int $id)
    {
        $website = $this->websiteModel->withRelations($id);

        if ($website === null || $this->outOfScope(self::COMPANY_SCOPED_ROLES, $website['company_id'])) {
            return redirect()->to('/websites')->with('error', 'Website not found.');
        }

        return view('App\Modules\Website\show', [
            'title'     => $website['domain'],
            'navActive' => 'websites',
            'website'   => $website,
        ]);
    }

    public function edit(int $id)
    {
        $website = $this->websiteModel->find($id);

        if ($website === null || $this->outOfScope(self::COMPANY_SCOPED_ROLES, $website['company_id'])) {
            return redirect()->to('/websites')->with('error', 'Website not found.');
        }

        return view('App\Modules\Website\form', $this->formData($website));
    }

    public function update(int $id)
    {
        $website = $this->websiteModel->find($id);

        if ($website === null || $this->outOfScope(self::COMPANY_SCOPED_ROLES, $website['company_id'])) {
            return redirect()->to('/websites')->with('error', 'Website not found.');
        }

        if (! $this->validate($this->websiteModel->getValidationRules())) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        if ($this->outOfScope(self::COMPANY_SCOPED_ROLES, (int) $this->request->getPost('company_id'))) {
            return redirect()->back()->withInput()->with('error', 'You can only assign websites to your own company.');
        }

        $this->websiteModel->update($id, $this->payload(false));
        $this->logActivity('website', 'update', $id, 'Updated website #' . $id);

        return redirect()->to('/websites/' . $id)->with('success', 'Website updated.');
    }

    public function delete(int $id)
    {
        $website = $this->websiteModel->find($id);

        if ($website === null || $this->outOfScope(self::COMPANY_SCOPED_ROLES, $website['company_id'])) {
            return redirect()->to('/websites')->with('error', 'Website not found.');
        }

        $this->websiteModel->delete($id);
        $this->logActivity('website', 'delete', $id, 'Deleted website #' . $id);

        return redirect()->to('/websites')->with('success', 'Website deleted.');
    }

    /**
     * Unlike bank account numbers, hosting/FTP credentials are things
     * someone genuinely needs to log in with day-to-day â€” so a
     * controlled reveal exists, gated to website.edit (stricter than
     * .view) and returned via POST/JSON rather than baked into the
     * page's HTML source.
     */
    public function reveal(int $id)
    {
        $website = $this->websiteModel->find($id);

        if ($website === null || $this->outOfScope(self::COMPANY_SCOPED_ROLES, $website['company_id'])) {
            return $this->response->setStatusCode(404)->setJSON(['error' => 'Not found.']);
        }

        $this->logActivity('website', 'update', $id, 'Revealed credentials for website #' . $id);

        return $this->response->setJSON([
            'admin_password' => $this->websiteModel->decrypt($website['admin_login_password_cipher']),
            'ftp_password'   => $this->websiteModel->decrypt($website['ftp_password_cipher']),
        ]);
    }

    private function formData(?array $website): array
    {
        return [
            'title'     => $website ? 'Edit Website' : 'Add Website',
            'navActive' => 'websites',
            'website'   => $website,
            'companies' => $this->scopedCompanyOptions($this->companyModel->optionsList()),
            'statuses'  => WebsiteModel::STATUSES,
            'defaultCompanyId' => null,
        ];
    }

    private function payload(bool $isNew): array
    {
        $data = [
            'company_id'           => (int) $this->request->getPost('company_id'),
            'domain'               => $this->request->getPost('domain'),
            'registrar'            => $this->request->getPost('registrar'),
            'hosting_provider'     => $this->request->getPost('hosting_provider'),
            'server_ip'            => $this->request->getPost('server_ip'),
            'dns_details'          => $this->request->getPost('dns_details'),
            'ssl_expiry'           => $this->request->getPost('ssl_expiry') ?: null,
            'renewal_date'         => $this->request->getPost('renewal_date') ?: null,
            'control_panel'        => $this->request->getPost('control_panel'),
            'control_panel_url'    => $this->request->getPost('control_panel_url'),
            'git_repository'       => $this->request->getPost('git_repository'),
            'ftp_host'             => $this->request->getPost('ftp_host'),
            'ftp_username'         => $this->request->getPost('ftp_username'),
            'admin_login_username' => $this->request->getPost('admin_login_username'),
            'status'               => $this->request->getPost('status') ?: 'active',
            'notes'                => $this->request->getPost('notes'),
        ];

        // Passwords are optional on edit â€” leave blank to keep the
        // existing stored value instead of overwriting it with nothing.
        $adminPassword = $this->request->getPost('admin_login_password');
        if ($adminPassword !== null && $adminPassword !== '') {
            $data['admin_login_password_cipher'] = $this->websiteModel->encrypt($adminPassword);
        }

        $ftpPassword = $this->request->getPost('ftp_password');
        if ($ftpPassword !== null && $ftpPassword !== '') {
            $data['ftp_password_cipher'] = $this->websiteModel->encrypt($ftpPassword);
        }

        if ($isNew) {
            $data['created_by'] = $this->currentUserId();
        }

        return $data;
    }
}
