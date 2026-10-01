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
        // A plain 'employee' now also carries onboarding.view (so they can
        // reach their own record via mine()/show()) but must never see the
        // company's full candidate pipeline — redirect them to their own
        // record instead of listing here.
        if (session('roleSlug') === 'employee') {
            return redirect()->to('/hr/onboarding/mine');
        }

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

    /**
     * "My Onboarding" — an ordinary employee's entry point to their own
     * record. Looked up via their linked employee_profiles row (never by
     * a record id they'd have to guess/be told), then handed off to the
     * same show() every staff role already uses — show() itself carries
     * the isSelfRecord() guard, so this stays safe even if the URL is
     * bookmarked or typed directly.
     */
    public function mine()
    {
        $profile = $this->employeeModel->byUserId($this->currentUserId());
        $record  = $profile !== null ? $this->recordModel->byEmployeeProfileId((int) $profile['id']) : null;

        if ($record === null) {
            return redirect()->to('/dashboard')->with('error', 'No onboarding record is linked to your profile yet.');
        }

        return $this->show((int) $record['id']);
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

        $isSelf = $this->isSelfRecord($record);

        // A plain 'employee' only holds onboarding.view for self-service —
        // outOfScope() above already confirms same-company, but that alone
        // would still let them open any colleague's candidate record, so
        // this closes the gap: staff roles pass through unaffected (they
        // never hit this branch), non-owner-non-self employees get sent
        // back to their own record instead.
        if (session('roleSlug') === 'employee' && ! $isSelf) {
            return redirect()->to('/hr/onboarding/mine')->with('error', 'You can only view your own onboarding record.');
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
            'isSelf'         => $isSelf,
            'myRole'         => session('roleSlug'),
            'isReportingManager' => (int) ($record['reporting_manager_id'] ?? 0) === (int) $this->currentUserId(),
            // For the "Link Employee Record" picker — only offered once
            // HR needs it (record['employee_profile_id'] still null),
            // scoped to the candidate's own company. Skipped entirely for
            // a non-owner viewer (e.g. self-view) since that block never
            // renders for them anyway — no point running the query.
            'employeeOptions' => ($this->isOwner() && ! $record['employee_profile_id'])
                ? $this->employeeModel->filtered(['company_id' => $record['company_id']])->findAll()
                : [],
            // Only a live (non-expired) link is shown/copyable — an
            // expired one is indistinguishable from "none issued yet"
            // as far as HR's next action goes (generate/resend either way).
            'portalUrl' => ($record['candidate_token'] && $record['token_expires_at'] >= date('Y-m-d H:i:s'))
                ? site_url("onboarding/portal/{$id}/{$record['candidate_token']}")
                : null,
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
        if (! $this->isOwner() && ! $this->isSelfRecord($record)) {
            return redirect()->to('/hr/onboarding/' . $id)->with('error', 'You do not have permission to view this offer letter.');
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
        if (! $this->isOwner() && ! $this->isSelfRecord($record)) {
            return redirect()->to('/hr/onboarding/' . $id)->with('error', 'You do not have permission to download this offer letter.');
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

    /**
     * Same access/rendering pattern as offerLetter() (staff or the
     * candidate themselves once linked), but only meaningful once the
     * candidate has actually joined — joined_at is the same timestamp
     * updateStatus() sets when the record first reaches 'joined', so
     * there's no separate "has a joining letter been issued" flag to
     * track; the letter is simply generated on demand from the record's
     * already-confirmed joining details.
     */
    public function joiningLetter(int $id)
    {
        $record = $this->recordModel->withRelations($id);

        if ($record === null || $this->outOfScope(self::COMPANY_SCOPED_ROLES, $record['company_id'])) {
            return redirect()->to('/hr/onboarding')->with('error', 'Onboarding record not found.');
        }
        if (! $this->isOwner() && ! $this->isSelfRecord($record)) {
            return redirect()->to('/hr/onboarding/' . $id)->with('error', 'You do not have permission to view this joining letter.');
        }
        if ($record['joined_at'] === null) {
            return redirect()->to('/hr/onboarding/' . $id)->with('error', 'The joining letter is available once this candidate has joined.');
        }

        return view('App\Modules\Onboarding\joining_letter', [
            'title'     => 'Joining Letter — ' . $record['candidate_name'],
            'record'    => $record,
            'company'   => $this->companyModel->find($record['company_id']),
            'autoPrint' => $this->request->getGet('print') === '1',
        ]);
    }

    public function joiningLetterPdf(int $id)
    {
        $record = $this->recordModel->withRelations($id);

        if ($record === null || $this->outOfScope(self::COMPANY_SCOPED_ROLES, $record['company_id'])) {
            return redirect()->to('/hr/onboarding')->with('error', 'Onboarding record not found.');
        }
        if (! $this->isOwner() && ! $this->isSelfRecord($record)) {
            return redirect()->to('/hr/onboarding/' . $id)->with('error', 'You do not have permission to download this joining letter.');
        }
        if ($record['joined_at'] === null) {
            return redirect()->to('/hr/onboarding/' . $id)->with('error', 'The joining letter is available once this candidate has joined.');
        }

        $options = new Options();
        $options->set('isRemoteEnabled', false);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml(view('App\Modules\Onboarding\exports\joining_letter_pdf', [
            'record'  => $record,
            'company' => $this->companyModel->find($record['company_id']),
        ]));
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $this->response
            ->setContentType('application/pdf')
            ->setHeader('Content-Disposition', 'attachment; filename="joining-letter-' . url_title($record['candidate_name'], '-', true) . '.pdf"')
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

    /**
     * "Send onboarding link" — real delivery via the in-app Notification
     * system (this app has no working SMTP: .env's SMTPUser/SMTPPass are
     * blank and nothing anywhere calls Config\Services::email(), so an
     * "email sent" claim here would be fabricated). Only works once the
     * candidate has a real login (employee_profile_id linked) — before
     * that there is no account to notify and no separate public/
     * token-based candidate portal exists to email a link to; the error
     * message says so plainly rather than silently no-opping.
     */
    public function sendLink(int $id)
    {
        $record = $this->recordModel->find($id);

        if ($record === null || $this->outOfScope(self::COMPANY_SCOPED_ROLES, $record['company_id'])) {
            return redirect()->to('/hr/onboarding')->with('error', 'Onboarding record not found.');
        }

        if (! $this->isOwner()) {
            return redirect()->to('/hr/onboarding/' . $id)->with('error', 'Only HR can send the onboarding link.');
        }

        if (empty($record['employee_profile_id'])) {
            return redirect()->to('/hr/onboarding/' . $id)->with('error', 'Link this candidate to an employee record first — there is no login to notify until then.');
        }

        $employee = $this->employeeModel->find((int) $record['employee_profile_id']);

        if ($employee === null || empty($employee['user_id'])) {
            return redirect()->to('/hr/onboarding/' . $id)->with('error', 'Could not find the linked employee\'s account.');
        }

        $this->notificationService->notify(
            (int) $employee['user_id'],
            'onboarding_link',
            'Your Onboarding Information',
            'You can now view your onboarding progress, offer letter and joining letter from your account.',
            'onboarding',
            $id
        );
        $this->logActivity('onboarding', 'update', $id, 'Sent the onboarding link notification to ' . $record['candidate_name']);

        return redirect()->to('/hr/onboarding/' . $id)->with('success', 'Onboarding link sent — delivered in-app (this instance has no email/SMTP configured), visible next time they log in.');
    }

    /**
     * Generates (or regenerates — an old link stops working the moment
     * a new one is issued, see OnboardingRecordModel::issueToken()) the
     * secure candidate-portal link and tries to actually email it.
     * Distinct from sendLink() above, which only ever worked once a
     * candidate already had a real ERP login linked — this is for the
     * pre-hire stage that has none.
     */
    public function sendPortalLink(int $id)
    {
        $record = $this->recordModel->find($id);

        if ($record === null || $this->outOfScope(self::COMPANY_SCOPED_ROLES, $record['company_id'])) {
            return redirect()->to('/hr/onboarding')->with('error', 'Onboarding record not found.');
        }
        if (! $this->isOwner()) {
            return redirect()->to('/hr/onboarding/' . $id)->with('error', 'Only HR can send the onboarding portal link.');
        }

        $token     = $this->recordModel->issueToken($id);
        $portalUrl = site_url("onboarding/portal/{$id}/{$token}");

        $this->logActivity('onboarding', 'update', $id, 'Generated a new onboarding portal link for ' . $record['candidate_name']);

        $sent = $this->attemptEmail(
            $record['candidate_email'],
            'Your Onboarding — ' . $record['candidate_name'],
            "Hello {$record['candidate_name']},\n\nPlease use the link below to verify your details" .
                ($record['offer_sent_at'] !== null ? ' and review your offer' : '') .
                ":\n\n{$portalUrl}\n\nThis link is valid for 7 days."
        );

        $message = $sent
            ? 'Portal link generated and emailed to ' . $record['candidate_email'] . '.'
            : 'Portal link generated (shown below to copy/share — email delivery is not configured on this server, so it was not sent automatically).';

        return redirect()->to('/hr/onboarding/' . $id)->with('success', $message);
    }

    /**
     * HR's decision on what the candidate submitted through the portal
     * (STEP 4). 'verified' leaves verification_notes exactly as the
     * candidate left it — nothing here needs to overwrite their note.
     * 'correction_needed' replaces it with HR's own message instead,
     * which is what the portal then shows the candidate on their next
     * visit (see portal/show.php) — the candidate's original note has
     * already served its purpose once HR has read it here.
     */
    public function reviewVerification(int $id)
    {
        $record = $this->recordModel->find($id);

        if ($record === null || $this->outOfScope(self::COMPANY_SCOPED_ROLES, $record['company_id'])) {
            return redirect()->to('/hr/onboarding')->with('error', 'Onboarding record not found.');
        }
        if (! $this->isOwner()) {
            return redirect()->to('/hr/onboarding/' . $id)->with('error', 'Only HR can review candidate verification.');
        }
        // Deliberately NOT restricted to verification_status === 'submitted'
        // only — HR must always be able to approve a candidate's details
        // (e.g. one who never used the portal at all, or was verified
        // by phone/in person), since the Offer Letter step is gated on
        // this being 'verified' (see OnboardingPortalController) and
        // that gate must never become a dead end HR can't get past.
        if ($record['verification_status'] === 'verified') {
            return redirect()->to('/hr/onboarding/' . $id)->with('error', 'Already verified.');
        }

        $decision = $this->request->getPost('decision');
        if (! in_array($decision, ['verified', 'correction_needed'], true)) {
            return redirect()->to('/hr/onboarding/' . $id)->with('error', 'Invalid decision.');
        }

        $data = [
            'verification_status'       => $decision,
            'verification_reviewed_by'  => $this->currentUserId(),
            'verification_reviewed_at'  => date('Y-m-d H:i:s'),
        ];

        if ($decision === 'correction_needed') {
            $data['verification_notes'] = $this->request->getPost('notes') ?: 'Please review and resubmit your details.';
        }

        $this->recordModel->update($id, $data);
        $this->logActivity(
            'onboarding',
            'update',
            $id,
            ($decision === 'verified' ? 'Verified' : 'Requested a correction on') . ' candidate-submitted information for ' . $record['candidate_name']
        );

        return redirect()->to('/hr/onboarding/' . $id)->with('success', $decision === 'verified' ? 'Marked as verified.' : 'Correction requested — the candidate will see your note.');
    }

    /**
     * Attempts a real send via CI4's own Email service — only when SMTP
     * is actually configured (an unconfigured blank host would otherwise
     * hang the request trying to connect, and .env's email.SMTPHost is
     * blank by default in this app, per the "no working SMTP" precedent
     * documented on sendLink() above). Returns false on any failure so
     * the caller can fall back to showing the link for manual sharing
     * instead of ever claiming delivery that didn't happen.
     */
    private function attemptEmail(string $to, string $subject, string $message): bool
    {
        $config = config('Email');

        // Host alone isn't enough — this app's .env ships a real
        // smtp.gmail.com host with a deliberately blank SMTPUser/
        // SMTPPass (no account attached yet), and attempting a connect
        // with a real hostname but no credentials would still hang the
        // request on a doomed handshake instead of failing fast.
        if (empty($config->SMTPHost) || empty($config->SMTPUser) || empty($config->SMTPPass) || ! filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        try {
            $email = \Config\Services::email();
            $email->setTo($to);
            $email->setSubject($subject);
            $email->setMessage(nl2br(esc($message)));

            return $email->send();
        } catch (\Throwable $e) {
            log_message('error', 'Onboarding portal email failed: ' . $e->getMessage());

            return false;
        }
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

    /**
     * True when $record is linked to an employee_profiles row belonging
     * to the currently logged-in user — the candidate viewing their own
     * onboarding record. Unlinked candidates (no login yet) never match,
     * which is correct: only a real account can "self-view".
     */
    private function isSelfRecord(array $record): bool
    {
        if (empty($record['employee_profile_id'])) {
            return false;
        }

        $profile = $this->employeeModel->find((int) $record['employee_profile_id']);

        return $profile !== null && (int) $profile['user_id'] === (int) $this->currentUserId();
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
