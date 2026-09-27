<?php

namespace App\Modules\Compliance\Controllers;

use App\Controllers\BaseController;
use App\Modules\Auth\Models\UserModel;
use App\Modules\Company\Models\CompanyModel;
use App\Modules\Compliance\Models\ComplianceAttachmentModel;
use App\Modules\Compliance\Models\ComplianceItemModel;
use App\Modules\Compliance\Models\ComplianceTypeModel;
use App\Modules\Compliance\Services\ComplianceRecurrenceService;
use App\Modules\Notification\Services\NotificationService;

class ComplianceController extends BaseController
{
    protected ComplianceItemModel $itemModel;
    protected ComplianceTypeModel $typeModel;
    protected ComplianceAttachmentModel $attachmentModel;
    protected ComplianceRecurrenceService $recurrenceService;
    protected CompanyModel $companyModel;
    protected UserModel $userModel;
    protected NotificationService $notificationService;

    public function __construct()
    {
        $this->itemModel         = new ComplianceItemModel();
        $this->typeModel         = new ComplianceTypeModel();
        $this->attachmentModel   = new ComplianceAttachmentModel();
        $this->recurrenceService = new ComplianceRecurrenceService();
        $this->companyModel      = new CompanyModel();
        $this->userModel         = new UserModel();
        $this->notificationService = new NotificationService();
    }

    public function index()
    {
        $this->itemModel->refreshOverdueStatuses();

        $filters = array_filter($this->request->getGet(['company_id', 'compliance_type_id', 'status', 'responsible_user_id']) ?? []);

        $companyScope = $this->companyScopeFor(self::COMPANY_SCOPED_ROLES);
        if ($companyScope !== null) {
            $filters['company_id'] = $companyScope;
        }

        return view('App\Modules\Compliance\index', [
            'title'     => 'Compliance',
            'navActive' => 'compliance',
            'items'     => $this->itemModel->filtered($filters)->findAll(),
            'companies' => $this->scopedCompanyOptions($this->companyModel->optionsList()),
            'types'     => $this->typeModel->optionsList(),
            'users'     => $this->scopedUserOptions($this->userModel->listForOptions()),
            'statuses'  => ComplianceItemModel::STATUSES,
            'filters'   => $filters,
        ]);
    }

    public function create()
    {
        $companyScope = $this->companyScopeFor(self::COMPANY_SCOPED_ROLES);
        $defaultCompanyId = $companyScope ?? $this->request->getGet('company_id');

        return view('App\Modules\Compliance\form', $this->formData(null, $defaultCompanyId ? (int) $defaultCompanyId : null));
    }

    public function store()
    {
        if (! $this->validate($this->itemModel->getValidationRules())) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        if ($this->outOfScope(self::COMPANY_SCOPED_ROLES, (int) $this->request->getPost('company_id'))) {
            return redirect()->to('/compliance')->with('error', 'You can only manage your own company.');
        }

        $id = $this->itemModel->insert($this->itemPayload(true));
        $this->logActivity('compliance', 'create', $id, 'Created compliance item #' . $id);
        $this->notifyResponsible((int) $id, $this->request->getPost('title') ?: 'Compliance item', $this->request->getPost('responsible_user_id') ?: null);

        return redirect()->to('/compliance/' . $id)->with('success', 'Compliance item created.');
    }

    public function show(int $id)
    {
        $item = $this->itemModel->withRelations($id);

        if ($item === null || $this->outOfScope(self::COMPANY_SCOPED_ROLES, $item['company_id'])) {
            return redirect()->to('/compliance')->with('error', 'Compliance item not found.');
        }

        return view('App\Modules\Compliance\show', [
            'title'       => $item['title'] ?: $item['type_name'],
            'navActive'   => 'compliance',
            'item'        => $item,
            'attachments' => $this->attachmentModel->forItem($id),
            'history'     => $this->historyChain($item),
        ]);
    }

    public function edit(int $id)
    {
        $item = $this->itemModel->find($id);

        if ($item === null || $this->outOfScope(self::COMPANY_SCOPED_ROLES, $item['company_id'])) {
            return redirect()->to('/compliance')->with('error', 'Compliance item not found.');
        }

        return view('App\Modules\Compliance\form', $this->formData($item));
    }

    public function update(int $id)
    {
        $existing = $this->itemModel->find($id);

        if ($existing === null || $this->outOfScope(self::COMPANY_SCOPED_ROLES, $existing['company_id'])) {
            return redirect()->to('/compliance')->with('error', 'Compliance item not found.');
        }

        if (! $this->validate($this->itemModel->getValidationRules())) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        if ($this->outOfScope(self::COMPANY_SCOPED_ROLES, (int) $this->request->getPost('company_id'))) {
            return redirect()->to('/compliance')->with('error', 'You can only manage your own company.');
        }

        $newResponsible = $this->request->getPost('responsible_user_id') ?: null;

        $this->itemModel->update($id, $this->itemPayload(false));
        $this->logActivity('compliance', 'update', $id, 'Updated compliance item #' . $id);

        if ((int) $newResponsible !== (int) $existing['responsible_user_id']) {
            $this->notifyResponsible($id, $this->request->getPost('title') ?: 'Compliance item', $newResponsible);
        }

        return redirect()->to('/compliance/' . $id)->with('success', 'Compliance item updated.');
    }

    public function delete(int $id)
    {
        if ($this->authorizedItem($id) === null) {
            return redirect()->to('/compliance')->with('error', 'Compliance item not found.');
        }

        $this->itemModel->delete($id);
        $this->logActivity('compliance', 'delete', $id, 'Deleted compliance item #' . $id);

        return redirect()->to('/compliance')->with('success', 'Compliance item deleted.');
    }

    public function markFiled(int $id)
    {
        if ($this->authorizedItem($id) === null) {
            return redirect()->to('/compliance')->with('error', 'Compliance item not found.');
        }

        try {
            $result = $this->recurrenceService->markFiled($id, (int) $this->currentUserId());
        } catch (\RuntimeException $e) {
            return redirect()->to('/compliance')->with('error', $e->getMessage());
        }

        $this->logActivity('compliance', 'update', $id, 'Marked compliance item #' . $id . ' as filed');

        $message = $result['next_item_id']
            ? 'Marked as filed. Next cycle scheduled automatically.'
            : 'Marked as filed.';

        return redirect()->to('/compliance/' . $id)->with('success', $message);
    }

    public function uploadAttachment(int $id)
    {
        if ($this->authorizedItem($id) === null) {
            return redirect()->to('/compliance')->with('error', 'Compliance item not found.');
        }

        $file = $this->request->getFile('attachment');

        if ($file === null || ! $file->isValid()) {
            return redirect()->back()->with('error', 'Please choose a valid file.');
        }

        if ($file->getSize() > 20 * 1024 * 1024) {
            return redirect()->back()->with('error', 'File exceeds the 20MB limit.');
        }

        $storage = service('fileStorage');
        $path    = $storage->store($file, "compliance/{$id}");

        $this->attachmentModel->insert([
            'compliance_item_id' => $id,
            'file_path'          => $path,
            'original_name'      => $file->getClientName(),
            'file_size'          => $file->getSize(),
            'uploaded_by'        => $this->currentUserId(),
        ]);

        $this->logActivity('compliance', 'update', $id, 'Uploaded attachment: ' . $file->getClientName());

        return redirect()->to('/compliance/' . $id)->with('success', 'Attachment uploaded.');
    }

    public function deleteAttachment(int $id, int $attachmentId)
    {
        if ($this->authorizedItem($id) === null) {
            return redirect()->to('/compliance')->with('error', 'Compliance item not found.');
        }

        $attachment = $this->attachmentModel->find($attachmentId);

        if ($attachment && (int) $attachment['compliance_item_id'] === $id) {
            service('fileStorage')->delete($attachment['file_path']);
            $this->attachmentModel->delete($attachmentId);
        }

        return redirect()->to('/compliance/' . $id)->with('success', 'Attachment removed.');
    }

    /**
     * Same purpose as TaskController::authorizedTask() — mark-filed and
     * the attachment actions all take a compliance item id from the
     * URL, so each re-checks it's within the viewer's company scope
     * before touching it.
     */
    private function authorizedItem(int $id): ?array
    {
        $item = $this->itemModel->find($id);

        if ($item === null || $this->outOfScope(self::COMPANY_SCOPED_ROLES, $item['company_id'])) {
            return null;
        }

        return $item;
    }

    private function notifyResponsible(int $itemId, string $title, ?string $responsibleUserId): void
    {
        if (! $responsibleUserId || (int) $responsibleUserId === (int) $this->currentUserId()) {
            return;
        }

        $this->notificationService->notify(
            (int) $responsibleUserId,
            'compliance_assigned',
            'Compliance item assigned: ' . $title,
            'You are now responsible for "' . $title . '".',
            'compliance',
            $itemId
        );
    }

    private function formData(?array $item, ?int $defaultCompanyId = null): array
    {
        return [
            'title'             => $item ? 'Edit Compliance Item' : 'Add Compliance Item',
            'navActive'         => 'compliance',
            'item'              => $item,
            'defaultCompanyId'  => $defaultCompanyId,
            'companies'         => $this->scopedCompanyOptions($this->companyModel->optionsList()),
            'types'             => $this->typeModel->optionsList(),
            'users'             => $this->scopedUserOptions($this->userModel->listForOptions()),
            'statuses'          => ComplianceItemModel::STATUSES,
        ];
    }

    private function itemPayload(bool $isNew): array
    {
        $data = [
            'company_id'           => (int) $this->request->getPost('company_id'),
            'compliance_type_id'   => (int) $this->request->getPost('compliance_type_id'),
            'title'                => $this->request->getPost('title'),
            'regulator'            => $this->request->getPost('regulator') ?: null,
            'period'               => $this->request->getPost('period') ?: null,
            'due_date'             => $this->request->getPost('due_date'),
            'recurrence'           => $this->request->getPost('recurrence') ?: 'none',
            'responsible_user_id'  => $this->request->getPost('responsible_user_id') ?: null,
            'reminder_days_before' => $this->request->getPost('reminder_days_before') ?: 7,
            'notes'                => $this->request->getPost('notes'),
        ];

        if ($isNew) {
            $data['status']     = 'pending';
            $data['created_by'] = $this->currentUserId();
        }

        return $data;
    }

    /**
     * Walks previous_item_id backwards so the show page can display
     * "filed on ... â†’ this cycle" without a recursive SQL query.
     */
    private function historyChain(array $item): array
    {
        $chain   = [];
        $cursor  = $item['previous_item_id'];
        $guard   = 0;

        while ($cursor !== null && $guard < 24) {
            $prev = $this->itemModel->withRelations((int) $cursor);

            if ($prev === null) {
                break;
            }

            $chain[] = $prev;
            $cursor  = $prev['previous_item_id'];
            $guard++;
        }

        return $chain;
    }
}
