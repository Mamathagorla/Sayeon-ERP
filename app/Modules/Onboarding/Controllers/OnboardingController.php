<?php

namespace App\Modules\Onboarding\Controllers;

use App\Controllers\BaseController;
use App\Modules\Auth\Models\UserModel;
use App\Modules\Company\Models\CompanyModel;
use App\Modules\Department\Models\DepartmentModel;
use App\Modules\Document\Models\DocumentModel;
use App\Modules\HR\Models\EmployeeProfileModel;
use App\Modules\HR\Models\SalaryStructureModel;
use App\Modules\Notification\Services\NotificationService;
use App\Modules\Onboarding\Models\OnboardingRecordModel;
use App\Modules\Onboarding\Models\OnboardingTaskModel;
use Dompdf\Dompdf;
use Dompdf\Options;

class OnboardingController extends BaseController
{
    /**
     * Roles allowed to control the main record (candidate info + pipeline
     * stage) — HR is the primary owner per spec; Company Admin/Manager/
     * Accountant hold onboarding.edit too, but only to complete their own
     * checklist tasks (see OnboardingTaskController), not to touch the
     * record itself. Checked in edit()/update()/updateStatus()/destroy()
     * in addition to (not instead of) the route's permission filter —
     * same layered pattern as LeaveController::ROLE_RANK.
     */
    private const OWNER_ROLES = ['hr', 'super_admin'];

    protected OnboardingRecordModel $recordModel;
    protected OnboardingTaskModel $taskModel;
    protected CompanyModel $companyModel;
    protected DepartmentModel $departmentModel;
    protected EmployeeProfileModel $employeeModel;
    protected SalaryStructureModel $salaryModel;
    protected DocumentModel $documentModel;
    protected UserModel $userModel;
    protected NotificationService $notificationService;

    public function __construct()
    {
        $this->recordModel         = new OnboardingRecordModel();
        $this->taskModel           = new OnboardingTaskModel();
        $this->companyModel        = new CompanyModel();
        $this->departmentModel     = new DepartmentModel();
        $this->employeeModel       = new EmployeeProfileModel();
        $this->salaryModel         = new SalaryStructureModel();
        $this->documentModel       = new DocumentModel();
        $this->userModel           = new UserModel();
        $this->notificationService = new NotificationService();
    }

    public function index()
    {
        $getFilters = array_filter($this->request->getGet(['status', 'department_id', 'q', 'joining_from']) ?? []);

        // The Status filter/column only deals in the 3 simplified buckets
        // (see OnboardingRecordModel::SIMPLE_BUCKETS) — translate that
        // into the real stage list before it reaches filtered(). The
        // filter UI is multi-select (one or more buckets checked), but a
        // bookmarked/legacy link with a bare ?status=completed (scalar,
        // no []) still works — (array) casts either shape the same way.
        // With no bucket chosen, default to every real stage (STATUSES
        // already excludes 'withdrawn'), so withdrawn candidates never
        // appear on this simplified list.
        $requestedBuckets = array_intersect((array) ($getFilters['status'] ?? []), array_keys(OnboardingRecordModel::SIMPLE_BUCKETS));

        $filters = $getFilters;
        $filters['status'] = $requestedBuckets !== []
            ? array_unique(array_merge(...array_map(static fn (string $b) => OnboardingRecordModel::SIMPLE_BUCKETS[$b], $requestedBuckets)))
            : OnboardingRecordModel::STATUSES;

        $companyScope = $this->companyScopeFor(self::COMPANY_SCOPED_ROLES);
        if ($companyScope !== null) {
            $filters['company_id'] = $companyScope;
        }

        $roleSlug = session('roleSlug');
        $records  = $this->recordModel->filtered($filters)->findAll();

        return view('App\Modules\Onboarding\index', [
            'title'        => 'Onboarding',
            'navActive'    => 'hr-onboarding',
            'records'      => $records,
            'progress'     => $this->taskModel->progressByRecord(array_column($records, 'id')),
            'statusCounts' => $this->recordModel->simpleCounts($companyScope),
            'departments'  => $this->departmentModel->optionsList(),
            'isOwner'      => in_array($roleSlug, self::OWNER_ROLES, true),
            'filters'      => $getFilters,
        ]);
    }

    public function create()
    {
        if (! $this->isOwner()) {
            return redirect()->to('/hr/onboarding')->with('error', 'Only HR can add a new onboarding record.');
        }

        return view('App\Modules\Onboarding\form', $this->formData(null));
    }

    public function store()
    {
        if (! $this->isOwner()) {
            return redirect()->to('/hr/onboarding')->with('error', 'Only HR can add a new onboarding record.');
        }

        if (! $this->validate($this->recordModel->getValidationRules(), $this->recordModel->getValidationMessages())) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $companyId = (int) $this->request->getPost('company_id');

        if ($this->outOfScope(self::COMPANY_SCOPED_ROLES, $companyId)) {
            return redirect()->back()->withInput()->with('error', 'You can only add candidates to your own company.');
        }

        $id = $this->recordModel->insert([
            'company_id'           => $companyId,
            'candidate_name'       => $this->request->getPost('candidate_name'),
            'candidate_email'      => $this->request->getPost('candidate_email'),
            'candidate_phone'      => $this->request->getPost('candidate_phone') ?: null,
            'department_id'        => $this->request->getPost('department_id') ?: null,
            'designation'          => $this->request->getPost('designation') ?: null,
            'offered_ctc'          => $this->request->getPost('offered_ctc') ?: null,
            'reporting_manager_id' => $this->request->getPost('reporting_manager_id') ?: null,
            'created_by'           => $this->currentUserId(),
        ]);

        $this->taskModel->seedDefaultTasks($id);
        $this->logActivity('onboarding', 'create', $id, 'Added onboarding candidate: ' . $this->request->getPost('candidate_name'));

        $managerId = $this->request->getPost('reporting_manager_id');
        if ($managerId) {
            $this->notificationService->notify(
                (int) $managerId,
                'onboarding_assigned',
                'Onboarding: ' . $this->request->getPost('candidate_name'),
                'You have been set as the onboarding manager for ' . $this->request->getPost('candidate_name') . '.',
                'onboarding',
                $id
            );
        }

        return redirect()->to('/hr/onboarding/' . $id)->with('success', 'Candidate added to the onboarding pipeline.');
    }

    public function show(int $id)
    {
        $record = $this->recordModel->withRelations($id);

        if ($record === null || $this->outOfScope(self::COMPANY_SCOPED_ROLES, $record['company_id'])) {
            return redirect()->to('/hr/onboarding')->with('error', 'Onboarding record not found.');
        }

        $tasks = $this->taskModel->forRecord($id);
        // Grouped by PHASE (when it happens), not department — the
        // checklist's primary organizing axis per the redesign; each
        // task still carries its own owner_role for the small "owner"
        // label shown per row. Phases (name/order/active) now come from
        // OnboardingPhaseModel (see OnboardingPhaseController), not the
        // old hardcoded PHASES constant, so HR's Add/Edit/Reorder/
        // Delete changes show up here immediately. Inactive phases
        // don't get their own section; any task that ends up with no
        // active phase (deactivated/deleted phase, or none assigned)
        // still shows, grouped under a trailing "Other" — tasks are
        // never dropped from view just because their phase went away.
        $phaseModel   = new \App\Modules\Onboarding\Models\OnboardingPhaseModel();
        $activePhases = $phaseModel->ordered(true);

        $tasksByPhase = [];
        foreach ($activePhases as $p) {
            $tasksByPhase[$p['name']] = [];
        }
        $other = [];
        foreach ($tasks as $task) {
            if ($task['phase_name'] !== null && array_key_exists($task['phase_name'], $tasksByPhase)) {
                $tasksByPhase[$task['phase_name']][] = $task;
            } else {
                $other[] = $task;
            }
        }
        if ($other !== []) {
            $tasksByPhase['Other'] = $other;
        }

        return view('App\Modules\Onboarding\show', [
            'title'          => $record['candidate_name'],
            'navActive'      => 'hr-onboarding',
            'record'         => $record,
            'tasksByPhase'   => $tasksByPhase,
            'ownerRoleLabels' => OnboardingTaskModel::OWNER_ROLE_LABELS,
            'stages'         => OnboardingRecordModel::STATUSES,
            'stageLabels'    => OnboardingRecordModel::STAGE_LABELS,
            'stageGroups'    => OnboardingRecordModel::STAGE_GROUPS,
            'nextStatus'     => $this->recordModel->nextStatus($record['status']),
            'documents'      => $this->documentModel->filtered(['onboarding_record_id' => $id])->findAll(),
            'activity'       => $this->activityFor($id),
            'salaryConfigured' => $record['employee_profile_id']
                ? $this->salaryModel->byUserId($this->employeeUserIdFor($record)) !== null
                : false,
            'isOwner'        => $this->isOwner(),
            'myRole'         => session('roleSlug'),
            'isReportingManager' => (int) ($record['reporting_manager_id'] ?? 0) === (int) $this->currentUserId(),
            // For the "Link Employee Record" picker — only offered once
            // HR needs it (record['employee_profile_id'] still null),
            // scoped to the candidate's own company.
            'employeeOptions' => $record['employee_profile_id']
                ? []
                : $this->employeeModel->filtered(['company_id' => $record['company_id']])->findAll(),
        ]);
    }

    /**
     * The Activity tab's feed — every activity_logs row BaseController::
     * logActivity() has written for this record (create/update/delete on
     * the record itself, stage changes, checklist updates), newest
     * first, with the actor's name joined in. No new table: this is the
     * first place activity_logs is surfaced in the UI, reading the same
     * audit trail every module already writes to.
     */
    private function activityFor(int $recordId): array
    {
        return db_connect()->table('activity_logs')
            ->select('activity_logs.*, users.name as user_name')
            ->join('users', 'users.id = activity_logs.user_id', 'left')
            ->where('activity_logs.module', 'onboarding')
            ->where('activity_logs.record_id', $recordId)
            ->orderBy('activity_logs.created_at', 'DESC')
            ->get()
            ->getResultArray();
    }

    /**
     * The offer letter itself — a standalone printable page (same
     * pattern as InvoiceController::print(): its own minimal HTML/CSS,
     * a floating Print button that just calls window.print(), no
     * separate PDF file generated or stored). Staff-only (never the
     * candidate, who has no login yet) — HR can preview/print it at any
     * stage once a CTC has been entered, independent of whether the
     * record's own status has been advanced to "Offer Sent" yet.
     */
    public function offerLetter(int $id)
    {
        $record = $this->recordModel->withRelations($id);

        if ($record === null || $this->outOfScope(self::COMPANY_SCOPED_ROLES, $record['company_id'])) {
            return redirect()->to('/hr/onboarding')->with('error', 'Onboarding record not found.');
        }
        if (! $this->isOwner()) {
            return redirect()->to('/hr/onboarding/' . $id)->with('error', 'Only HR can view the offer letter.');
        }

        return view('App\Modules\Onboarding\offer_letter', [
            'title'     => 'Offer Letter — ' . $record['candidate_name'],
            'record'    => $record,
            'company'   => $this->companyModel->find($record['company_id']),
            // The "Print Offer Letter" button (see show.php) links here
            // with ?print=1 so the print dialog opens immediately; the
            // plain "View Offer Letter" button omits it, leaving this
            // the same read-only page either way — same document, just
            // whether the browser's print dialog fires on load.
            'autoPrint' => $this->request->getGet('print') === '1',
        ]);
    }

    /**
     * Same document as offerLetter(), rendered to a downloadable PDF via
     * a separate Dompdf-safe export view (table layout, no flex/grid —
     * see PayrollController::payslipPdf() for the same pattern).
     */
    public function offerLetterPdf(int $id)
    {
        $record = $this->recordModel->withRelations($id);

        if ($record === null || $this->outOfScope(self::COMPANY_SCOPED_ROLES, $record['company_id'])) {
            return redirect()->to('/hr/onboarding')->with('error', 'Onboarding record not found.');
        }
        if (! $this->isOwner()) {
            return redirect()->to('/hr/onboarding/' . $id)->with('error', 'Only HR can download the offer letter.');
        }

        $options = new Options();
        $options->set('isRemoteEnabled', false);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml(view('App\Modules\Onboarding\exports\offer_letter_pdf', [
            'record'  => $record,
            'company' => $this->companyModel->find($record['company_id']),
        ]));
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $this->response
            ->setContentType('application/pdf')
            ->setHeader('Content-Disposition', 'attachment; filename="offer-letter-' . url_title($record['candidate_name'], '-', true) . '.pdf"')
            ->setBody($dompdf->output());
    }

    public function edit(int $id)
    {
        $record = $this->recordModel->find($id);

        if ($record === null || $this->outOfScope(self::COMPANY_SCOPED_ROLES, $record['company_id'])) {
            return redirect()->to('/hr/onboarding')->with('error', 'Onboarding record not found.');
        }

        if (! $this->isOwner()) {
            return redirect()->to('/hr/onboarding/' . $id)->with('error', 'Only HR can edit this candidate\'s details.');
        }

        return view('App\Modules\Onboarding\form', $this->formData($record));
    }

    public function update(int $id)
    {
        $record = $this->recordModel->find($id);

        if ($record === null || $this->outOfScope(self::COMPANY_SCOPED_ROLES, $record['company_id'])) {
            return redirect()->to('/hr/onboarding')->with('error', 'Onboarding record not found.');
        }

        if (! $this->isOwner()) {
            return redirect()->to('/hr/onboarding/' . $id)->with('error', 'Only HR can edit this candidate\'s details.');
        }

        $rules = $this->recordModel->getValidationRules();
        unset($rules['company_id']); // company is fixed once created — not editable here.

        if (! $this->validate($rules, $this->recordModel->getValidationMessages())) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $joiningDate = $this->request->getPost('joining_date') ?: null;
        if ($joiningDate && $joiningDate < date('Y-m-d', strtotime($record['created_at']))) {
            return redirect()->back()->withInput()->with('errors', ['Joining date cannot be before the candidate was added.']);
        }

        $this->recordModel->update($id, [
            'candidate_name'       => $this->request->getPost('candidate_name'),
            'candidate_email'      => $this->request->getPost('candidate_email'),
            'candidate_phone'      => $this->request->getPost('candidate_phone') ?: null,
            'department_id'        => $this->request->getPost('department_id') ?: null,
            'designation'          => $this->request->getPost('designation') ?: null,
            'offered_ctc'          => $this->request->getPost('offered_ctc') ?: null,
            'reporting_manager_id' => $this->request->getPost('reporting_manager_id') ?: null,
            'bgv_status'           => $this->request->getPost('bgv_status') ?: $record['bgv_status'],
            'bgv_notes'            => $this->request->getPost('bgv_notes') ?: null,
            'joining_date'         => $joiningDate,
            'probation_end_date'   => $this->request->getPost('probation_end_date') ?: null,
        ]);

        $this->logActivity('onboarding', 'update', $id, 'Updated onboarding candidate: ' . $this->request->getPost('candidate_name'));

        return redirect()->to('/hr/onboarding/' . $id)->with('success', 'Candidate details updated.');
    }

    public function updateStatus(int $id)
    {
        $record = $this->recordModel->find($id);

        if ($record === null || $this->outOfScope(self::COMPANY_SCOPED_ROLES, $record['company_id'])) {
            return redirect()->to('/hr/onboarding')->with('error', 'Onboarding record not found.');
        }

        if (! $this->isOwner()) {
            return redirect()->to('/hr/onboarding/' . $id)->with('error', 'Only HR can change the onboarding stage.');
        }

        $status = $this->request->getPost('status');
        $validStatuses = array_merge(OnboardingRecordModel::STATUSES, [OnboardingRecordModel::WITHDRAWN]);

        if (! in_array($status, $validStatuses, true)) {
            return redirect()->to('/hr/onboarding/' . $id)->with('error', 'Invalid stage.');
        }

        $data = ['status' => $status];

        if ($status === 'offer_sent' && $record['offer_sent_at'] === null) {
            $data['offer_sent_at'] = date('Y-m-d H:i:s');
        }
        if ($status === 'offer_accepted' && $record['offer_accepted_at'] === null) {
            $data['offer_accepted_at'] = date('Y-m-d H:i:s');
        }
        if ($status === 'ready_to_join' && $record['hr_verified_at'] === null) {
            $data['hr_verified_by'] = $this->currentUserId();
            $data['hr_verified_at'] = date('Y-m-d H:i:s');
        }
        if ($status === 'joined' && $record['joined_at'] === null) {
            $data['joined_at'] = date('Y-m-d H:i:s');
        }
        if ($status === 'confirmed' && $record['confirmed_at'] === null) {
            $data['confirmed_at'] = date('Y-m-d H:i:s');
        }

        $this->recordModel->update($id, $data);
        $this->logActivity('onboarding', 'update', $id, 'Moved ' . $record['candidate_name'] . ' to ' . OnboardingRecordModel::STAGE_LABELS[$status]);

        if (! empty($record['reporting_manager_id'])) {
            $this->notificationService->notify(
                (int) $record['reporting_manager_id'],
                'onboarding_status',
                'Onboarding update: ' . $record['candidate_name'],
                $record['candidate_name'] . ' is now at "' . OnboardingRecordModel::STAGE_LABELS[$status] . '".',
                'onboarding',
                $id
            );
        }

        return redirect()->to('/hr/onboarding/' . $id)->with('success', 'Stage updated to ' . OnboardingRecordModel::STAGE_LABELS[$status] . '.');
    }

    /**
     * Links this onboarding record to an already-created employee_profiles
     * row — deliberately manual and separate from EmployeeController's own
     * create flow (no auto-created logins from here) so HR reviews and
     * completes the real Employee record first, then comes back to close
     * the loop. Reuses the existing Employees module entirely; nothing
     * about employee creation is duplicated here.
     */
    public function linkEmployee(int $id)
    {
        $record = $this->recordModel->find($id);

        if ($record === null || $this->outOfScope(self::COMPANY_SCOPED_ROLES, $record['company_id'])) {
            return redirect()->to('/hr/onboarding')->with('error', 'Onboarding record not found.');
        }

        if (! $this->isOwner()) {
            return redirect()->to('/hr/onboarding/' . $id)->with('error', 'Only HR can link an employee record.');
        }

        $employeeProfileId = (int) $this->request->getPost('employee_profile_id');
        $employee = $this->employeeModel->find($employeeProfileId);

        if ($employee === null || (int) $employee['company_id'] !== (int) $record['company_id']) {
            return redirect()->to('/hr/onboarding/' . $id)->with('error', 'Select a valid employee from the same company.');
        }

        $this->recordModel->update($id, ['employee_profile_id' => $employeeProfileId]);
        $this->logActivity('onboarding', 'update', $id, 'Linked ' . $record['candidate_name'] . ' to employee record #' . $employeeProfileId);

        return redirect()->to('/hr/onboarding/' . $id)->with('success', 'Linked to the employee record.');
    }

    public function destroy(int $id)
    {
        $record = $this->recordModel->find($id);

        if ($record !== null) {
            if ($this->outOfScope(self::COMPANY_SCOPED_ROLES, $record['company_id'])) {
                return redirect()->to('/hr/onboarding')->with('error', 'Onboarding record not found.');
            }

            $this->recordModel->delete($id);
            $this->logActivity('onboarding', 'delete', $id, 'Removed onboarding candidate: ' . $record['candidate_name']);
        }

        return redirect()->to('/hr/onboarding')->with('success', 'Onboarding record removed.');
    }

    private function isOwner(): bool
    {
        return in_array(session('roleSlug'), self::OWNER_ROLES, true);
    }

    private function employeeUserIdFor(array $record): ?int
    {
        $employee = $this->employeeModel->find($record['employee_profile_id']);

        return $employee['user_id'] ?? null;
    }

    private function formData(?array $record): array
    {
        return [
            'title'       => $record ? 'Edit Candidate' : 'Add Onboarding Candidate',
            'navActive'   => 'hr-onboarding',
            'record'      => $record,
            'companies'   => $this->scopedCompanyOptions($this->companyModel->optionsList()),
            'departments' => $this->departmentModel->optionsList(),
            'managers'    => $this->scopedUserOptions($this->userModel->listForOptions()),
            'bgvStatuses' => OnboardingRecordModel::BGV_STATUSES,
        ];
    }
}
