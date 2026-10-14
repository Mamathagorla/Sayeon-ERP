<?php

namespace App\Modules\Compliance\Controllers;

use App\Controllers\BaseController;
use App\Modules\Auth\Models\UserModel;
use App\Modules\Company\Models\CompanyModel;
use App\Modules\Compliance\Models\CompanyComplianceTypeModel;
use App\Modules\Compliance\Models\ComplianceAttachmentModel;
use App\Modules\Compliance\Models\ComplianceItemModel;
use App\Modules\Compliance\Models\ComplianceTypeModel;
use App\Modules\Compliance\Services\ComplianceRecurrenceGenerator;
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
    protected CompanyComplianceTypeModel $companyTypeModel;

    public function __construct()
    {
        $this->itemModel         = new ComplianceItemModel();
        $this->typeModel         = new ComplianceTypeModel();
        $this->attachmentModel   = new ComplianceAttachmentModel();
        $this->recurrenceService = new ComplianceRecurrenceService();
        $this->companyModel      = new CompanyModel();
        $this->userModel         = new UserModel();
        $this->notificationService = new NotificationService();
        $this->companyTypeModel  = new CompanyComplianceTypeModel();
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

        if ($this->responsibleMismatch()) {
            return redirect()->back()->withInput()->with('error', 'The responsible person must belong to the selected company.');
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

        if ($this->responsibleMismatch()) {
            return redirect()->back()->withInput()->with('error', 'The responsible person must belong to the selected company.');
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

    /**
     * Manual, on-demand catch-up — same reasoning as
     * RecurringExpenseController::generateNow()/RecurringMeetingController::
     * generateNow(): scoped to the viewer's own company (or every company
     * for an unrestricted Super Admin), runs the exact same generator the
     * daily auto-trigger and the production cron/CLI command use.
     */
    public function generateRecurring()
    {
        $companyScope = $this->companyScopeFor(self::COMPANY_SCOPED_ROLES);

        $result = $companyScope !== null
            ? (new ComplianceRecurrenceGenerator())->runForCompany($companyScope)
            : (new ComplianceRecurrenceGenerator())->runAll();

        if ($result['itemsGenerated'] > 0) {
            $this->logActivity('compliance', 'create', null, "Generated the next cycle for {$result['itemsGenerated']} compliance item(s).");
        }

        $message = $result['itemsGenerated'] > 0
            ? "Generated the next cycle for {$result['itemsGenerated']} compliance item(s)."
            : 'Nothing due to generate right now.';

        return redirect()->to('/compliance')->with('success', $message);
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
    /** The responsible person must be an employee of the item's company. Blank is allowed. */
    private function responsibleMismatch(): bool
    {
        $userId = (int) $this->request->getPost('responsible_user_id');

        return $userId > 0 && db_connect()->table('employee_profiles')
            ->where('user_id', $userId)->where('company_id', (int) $this->request->getPost('company_id'))->countAllResults() === 0;
    }

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
        $allTypes = $this->typeModel->optionsList();

        // company_id => the types actually applicable to it — only for
        // companies that have configured a subset (see
        // CompanyComplianceTypeModel::applicableIdsByCompany()); a
        // company absent from this map has never configured one, so the
        // form's JS falls back to showing every type for it, exactly as
        // it did before this feature existed.
        $applicableByCompany = $this->companyTypeModel->applicableIdsByCompany();
        $typesByCompany = [];
        foreach ($applicableByCompany as $companyId => $typeIds) {
            $typesByCompany[$companyId] = array_values(array_filter(
                $allTypes,
                static fn (array $t) => in_array((int) $t['id'], $typeIds, true)
            ));
        }

        return [
            'title'             => $item ? 'Edit Compliance Item' : 'Add Compliance Item',
            'navActive'         => 'compliance',
            'item'              => $item,
            'defaultCompanyId'  => $defaultCompanyId,
            'companies'         => $this->scopedCompanyOptions($this->companyModel->optionsList()),
            'types'             => $allTypes,
            'typesByCompany'    => $typesByCompany,
            'users'             => $this->scopedUserOptions($this->userModel->listForOptions()),
            'statuses'          => ComplianceItemModel::STATUSES,
        ];
    }

    /**
     * "Which compliance types apply to this company" settings page —
     * checkboxes over the shared global type list, scoped the same way
     * every other admin screen picks a company (companyScopeFor() for a
     * scoped role, an explicit ?company_id= for an unrestricted Super
     * Admin).
     */
    public function typeSettings()
    {
        $companyScope = $this->companyScopeFor(self::COMPANY_SCOPED_ROLES);
        $companyId    = $companyScope ?? (int) ($this->request->getGet('company_id') ?: 0);

        $companies = $this->scopedCompanyOptions($this->companyModel->optionsList());
        if ($companyId === 0 && count($companies) > 0) {
            $companyId = (int) $companies[0]['id'];
        }

        if ($companyId !== 0 && $this->outOfScope(self::COMPANY_SCOPED_ROLES, $companyId)) {
            return redirect()->to('/compliance')->with('error', 'You can only manage your own company.');
        }

        return view('App\Modules\Compliance\type_settings', [
            'title'          => 'Company Compliance Types',
            'navActive'      => 'compliance',
            'companies'      => $companies,
            'selectedCompanyId' => $companyId,
            'types'          => $this->typeModel->optionsList(),
            'applicableIds'  => $companyId !== 0 ? $this->companyTypeModel->applicableIdsFor($companyId) : [],
            // Set by the "New" dialog after it adds a type, so the page can confirm it.
            'addedName'      => trim((string) $this->request->getGet('added')) ?: null,
        ]);
    }

    public function saveTypeSettings()
    {
        $companyId = (int) $this->request->getPost('company_id');

        if ($companyId === 0 || $this->outOfScope(self::COMPANY_SCOPED_ROLES, $companyId)) {
            return redirect()->to('/compliance')->with('error', 'You can only manage your own company.');
        }

        $typeIds = array_map('intval', $this->request->getPost('compliance_type_ids') ?? []);
        $this->companyTypeModel->setApplicable($companyId, $typeIds);
        $this->logActivity('compliance', 'update', null, 'Updated applicable compliance types for company #' . $companyId);

        return redirect()->to('/compliance/types/settings?company_id=' . $companyId)->with('success', 'Compliance type settings saved.');
    }

    /**
     * Inline "+ Add new type" on the Compliance item form — creates
     * (or reuses, see ComplianceTypeModel::findOrCreate()) a global type
     * and immediately marks it applicable to the posted company, then
     * hands the new option straight back as JSON so the form's JS can
     * append+select it without a page reload.
     */
    public function createType()
    {
        $name      = trim((string) $this->request->getPost('name'));
        $companyId = (int) $this->request->getPost('company_id');

        if ($name === '' || $companyId === 0 || $this->outOfScope(self::COMPANY_SCOPED_ROLES, $companyId)) {
            return $this->response->setStatusCode(422)->setJSON(['error' => 'Enter a name and a valid company.']);
        }

        $type = $this->typeModel->findOrCreate($name);

        // Only touch the junction table if this company has already
        // opted into an explicit subset — an empty set there means
        // "every type applies" (see applicableIdsByCompany()'s
        // docblock), and inserting just this one new type would
        // wrongly flip that company from "sees everything" to
        // "restricted to only this new type".
        $existing = $this->companyTypeModel->applicableIdsFor($companyId);
        if ($existing !== []) {
            $this->companyTypeModel->setApplicable($companyId, array_unique([...$existing, (int) $type['id']]));
        }

        return $this->response->setJSON(['id' => $type['id'], 'name' => $type['name']]);
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
