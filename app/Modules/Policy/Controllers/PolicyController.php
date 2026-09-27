<?php

namespace App\Modules\Policy\Controllers;

use App\Controllers\BaseController;
use App\Modules\Auth\Models\UserModel;
use App\Modules\Company\Models\CompanyModel;
use App\Modules\Document\Models\DocumentModel;
use App\Modules\Policy\Models\PolicyModel;

class PolicyController extends BaseController
{
    protected PolicyModel $policyModel;
    protected CompanyModel $companyModel;
    protected UserModel $userModel;
    protected DocumentModel $documentModel;

    public function __construct()
    {
        $this->policyModel   = new PolicyModel();
        $this->companyModel  = new CompanyModel();
        $this->userModel     = new UserModel();
        $this->documentModel = new DocumentModel();
    }

    public function index()
    {
        $filters = array_filter($this->request->getGet(['company_id', 'type', 'status']) ?? []);

        $companyScope = $this->companyScopeFor(self::COMPANY_SCOPED_ROLES);
        if ($companyScope !== null) {
            $filters['company_id'] = $companyScope;
        }

        return view('App\Modules\Policy\index', [
            'title'     => 'Policies & Manuals',
            'navActive' => 'policies',
            'policies'  => $this->policyModel->filtered($filters)->findAll(),
            'companies' => $this->scopedCompanyOptions($this->companyModel->optionsList()),
            'types'     => PolicyModel::TYPES,
            'statuses'  => PolicyModel::STATUSES,
            'filters'   => $filters,
        ]);
    }

    public function create()
    {
        return view('App\Modules\Policy\form', $this->formData(null));
    }

    public function store()
    {
        if (! $this->validate($this->policyModel->getValidationRules())) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $companyId = (int) $this->request->getPost('company_id');

        if ($this->outOfScope(self::COMPANY_SCOPED_ROLES, $companyId)) {
            return redirect()->back()->withInput()->with('error', 'You can only add policies for your own company.');
        }

        $id = $this->policyModel->insert([
            'company_id'       => $companyId,
            'title'            => $this->request->getPost('title'),
            'type'             => $this->request->getPost('type') ?: 'policy',
            'owner_id'         => $this->request->getPost('owner_id') ?: null,
            'version'          => $this->request->getPost('version') ?: null,
            'last_review_date' => $this->request->getPost('last_review_date') ?: null,
            'retention_years'  => $this->request->getPost('retention_years') ?: null,
            'status'           => $this->request->getPost('status') ?: 'draft',
            'created_by'       => $this->currentUserId(),
        ]);

        $this->logActivity('policy', 'create', $id, 'Added policy/manual: ' . $this->request->getPost('title'));

        return redirect()->to('/policies/' . $id)->with('success', 'Policy/manual added.');
    }

    public function show(int $id)
    {
        $policy = $this->policyModel->withRelations($id);

        if ($policy === null || $this->outOfScope(self::COMPANY_SCOPED_ROLES, $policy['company_id'])) {
            return redirect()->to('/policies')->with('error', 'Policy/manual not found.');
        }

        return view('App\Modules\Policy\show', [
            'title'     => $policy['title'],
            'navActive' => 'policies',
            'policy'    => $policy,
        ]);
    }

    public function edit(int $id)
    {
        $policy = $this->policyModel->find($id);

        if ($policy === null || $this->outOfScope(self::COMPANY_SCOPED_ROLES, $policy['company_id'])) {
            return redirect()->to('/policies')->with('error', 'Policy/manual not found.');
        }

        return view('App\Modules\Policy\form', $this->formData($policy));
    }

    public function update(int $id)
    {
        $policy = $this->policyModel->find($id);

        if ($policy === null || $this->outOfScope(self::COMPANY_SCOPED_ROLES, $policy['company_id'])) {
            return redirect()->to('/policies')->with('error', 'Policy/manual not found.');
        }

        $rules = $this->policyModel->getValidationRules();
        unset($rules['company_id']); // company is fixed once created, same as Onboarding/Offboarding.

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $this->policyModel->update($id, [
            'title'            => $this->request->getPost('title'),
            'type'             => $this->request->getPost('type') ?: 'policy',
            'owner_id'         => $this->request->getPost('owner_id') ?: null,
            'version'          => $this->request->getPost('version') ?: null,
            'last_review_date' => $this->request->getPost('last_review_date') ?: null,
            'retention_years'  => $this->request->getPost('retention_years') ?: null,
            'status'           => $this->request->getPost('status') ?: 'draft',
        ]);

        $this->logActivity('policy', 'update', $id, 'Updated policy/manual: ' . $this->request->getPost('title'));

        return redirect()->to('/policies/' . $id)->with('success', 'Policy/manual updated.');
    }

    public function delete(int $id)
    {
        $policy = $this->policyModel->find($id);

        if ($policy !== null) {
            if ($this->outOfScope(self::COMPANY_SCOPED_ROLES, $policy['company_id'])) {
                return redirect()->to('/policies')->with('error', 'Policy/manual not found.');
            }

            // The linked document (if any) is left in place — same
            // "don't destroy the underlying file just because the
            // record referencing it goes away" reasoning as Offboarding.
            $this->policyModel->delete($id);
            $this->logActivity('policy', 'delete', $id, 'Deleted policy/manual: ' . $policy['title']);
        }

        return redirect()->to('/policies')->with('success', 'Policy/manual removed.');
    }

    private function formData(?array $policy): array
    {
        return [
            'title'     => $policy ? 'Edit Policy/Manual' : 'Add Policy/Manual',
            'navActive' => 'policies',
            'policy'    => $policy,
            'companies' => $this->scopedCompanyOptions($this->companyModel->optionsList()),
            'owners'    => $this->scopedUserOptions($this->userModel->listForOptions()),
            'types'     => PolicyModel::TYPES,
            'statuses'  => PolicyModel::STATUSES,
        ];
    }
}
