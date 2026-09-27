<?php

namespace App\Modules\Document\Controllers;

use App\Controllers\BaseController;
use App\Modules\Auth\Models\UserModel;
use App\Modules\Company\Models\CompanyModel;
use App\Modules\Document\Models\DocumentModel;
use App\Modules\Policy\Models\PolicyModel;

class DocumentController extends BaseController
{
    protected DocumentModel $documentModel;
    protected CompanyModel $companyModel;
    protected UserModel $userModel;

    public function __construct()
    {
        $this->documentModel = new DocumentModel();
        $this->companyModel  = new CompanyModel();
        $this->userModel     = new UserModel();
    }

    public function index()
    {
        $filters = array_filter($this->request->getGet(['company_id', 'category']) ?? []);

        $companyScope = $this->companyScopeFor(self::COMPANY_SCOPED_ROLES);
        if ($companyScope !== null) {
            $filters['company_id'] = $companyScope;
        }

        return view('App\Modules\Document\index', [
            'title'      => 'Documents',
            'navActive'  => 'documents',
            'documents'  => $this->documentModel->filtered($filters)->findAll(),
            'companies'  => $this->scopedCompanyOptions($this->companyModel->optionsList()),
            'categories' => DocumentModel::CATEGORIES,
            'filters'    => $filters,
        ]);
    }

    public function create()
    {
        return view('App\Modules\Document\form', [
            'title'      => 'Upload Document',
            'navActive'  => 'documents',
            'companies'  => $this->scopedCompanyOptions($this->companyModel->optionsList()),
            'users'      => $this->scopedUserOptions($this->userModel->listForOptions()),
            'categories' => DocumentModel::CATEGORIES,
            'defaultCompanyId' => $this->request->getGet('company_id'),
            // Set when arriving from an Onboarding record's "Upload
            // Document" link — persisted as a hidden field so the
            // uploaded file links back to that candidate without a
            // second, duplicate upload flow inside the Onboarding module.
            'onboardingRecordId' => $this->request->getGet('onboarding_record_id'),
            // Set when arriving from an Offboarding record's "Upload"
            // link — just pre-selects the existing "Linked Employee"
            // dropdown, no new field/column needed (offboarding is
            // always for an existing employee_user_id already).
            'defaultEmployeeUserId' => $this->request->getGet('employee_user_id'),
            // Set when arriving from a Policy/Manual's "Attach
            // Document" link — same passthrough pattern as
            // onboarding_record_id above.
            'policyId' => $this->request->getGet('policy_id'),
        ]);
    }

    public function store()
    {
        $rules = $this->documentModel->getValidationRules();

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $companyId = (int) $this->request->getPost('company_id');

        if ($this->outOfScope(self::COMPANY_SCOPED_ROLES, $companyId)) {
            return redirect()->to('/documents')->with('error', 'You can only manage your own company.');
        }

        $file = $this->request->getFile('file');

        if ($file === null || ! $file->isValid()) {
            return redirect()->back()->withInput()->with('error', 'Please choose a valid file.');
        }

        if ($file->getSize() > 20 * 1024 * 1024) {
            return redirect()->back()->withInput()->with('error', 'File exceeds the 20MB limit.');
        }

        $storage = service('fileStorage');
        $path    = $storage->store($file, "company_documents/{$companyId}");

        $onboardingRecordId = $this->request->getPost('onboarding_record_id') ?: null;
        $policyId           = $this->request->getPost('policy_id') ?: null;

        $id = $this->documentModel->insert([
            'company_id'           => $companyId,
            'category'             => $this->request->getPost('category'),
            'document_type'        => $this->request->getPost('document_type'),
            'title'                => $this->request->getPost('title'),
            'employee_user_id'     => $this->request->getPost('employee_user_id') ?: null,
            'onboarding_record_id' => $onboardingRecordId,
            'policy_id'            => $policyId,
            'file_path'            => $path,
            'original_name'        => $file->getClientName(),
            'file_size'            => $file->getSize(),
            'expiry_date'          => $this->request->getPost('expiry_date') ?: null,
            'uploaded_by'          => $this->currentUserId(),
        ]);

        $this->logActivity('document', 'create', $id, 'Uploaded document: ' . $this->request->getPost('title'));

        if ($onboardingRecordId) {
            return redirect()->to('/hr/onboarding/' . $onboardingRecordId)->with('success', 'Document uploaded.');
        }
        if ($policyId) {
            // If this policy didn't already have a primary document,
            // this upload becomes it — closes the loop from the
            // "Attach Document" link without a second manual step.
            $policyModel = new PolicyModel();
            $policy      = $policyModel->find($policyId);
            if ($policy !== null && empty($policy['document_id'])) {
                $policyModel->update($policyId, ['document_id' => $id]);
            }

            return redirect()->to('/policies/' . $policyId)->with('success', 'Document uploaded.');
        }

        return redirect()->to('/documents')->with('success', 'Document uploaded.');
    }

    public function edit(int $id)
    {
        $document = $this->documentModel->find($id);

        if ($document === null || $this->outOfScope(self::COMPANY_SCOPED_ROLES, $document['company_id'])) {
            return redirect()->to('/documents')->with('error', 'Document not found.');
        }

        return view('App\Modules\Document\edit', [
            'title'      => 'Edit Document',
            'navActive'  => 'documents',
            'document'   => $document,
            'users'      => $this->scopedUserOptions($this->userModel->listForOptions()),
            'categories' => DocumentModel::CATEGORIES,
        ]);
    }

    public function update(int $id)
    {
        $document = $this->documentModel->find($id);

        if ($document === null || $this->outOfScope(self::COMPANY_SCOPED_ROLES, $document['company_id'])) {
            return redirect()->to('/documents')->with('error', 'Document not found.');
        }

        $rules = [
            'category' => 'required|in_list[legal,finance,hr,it,marketing,compliance,general]',
            'title'    => 'required|min_length[2]|max_length[150]',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $data = [
            'category'         => $this->request->getPost('category'),
            'document_type'    => $this->request->getPost('document_type'),
            'title'            => $this->request->getPost('title'),
            'employee_user_id' => $this->request->getPost('employee_user_id') ?: null,
            'expiry_date'      => $this->request->getPost('expiry_date') ?: null,
        ];

        $file = $this->request->getFile('file');

        if ($file !== null && $file->isValid()) {
            if ($file->getSize() > 20 * 1024 * 1024) {
                return redirect()->back()->withInput()->with('error', 'File exceeds the 20MB limit.');
            }

            $storage = service('fileStorage');
            $data['file_path']     = $storage->store($file, "company_documents/{$document['company_id']}");
            $data['original_name'] = $file->getClientName();
            $data['file_size']     = $file->getSize();
            $storage->delete($document['file_path']);
        }

        $this->documentModel->update($id, $data);
        $this->logActivity('document', 'update', $id, 'Updated document: ' . $data['title']);

        return redirect()->to('/documents')->with('success', 'Document updated.');
    }

    public function delete(int $id)
    {
        $document = $this->documentModel->find($id);

        if ($document !== null) {
            if ($this->outOfScope(self::COMPANY_SCOPED_ROLES, $document['company_id'])) {
                return redirect()->to('/documents')->with('error', 'Document not found.');
            }

            service('fileStorage')->delete($document['file_path']);
            $this->documentModel->delete($id);
            $this->logActivity('document', 'delete', $id, 'Deleted document: ' . $document['title']);
        }

        return redirect()->to('/documents')->with('success', 'Document deleted.');
    }
}
