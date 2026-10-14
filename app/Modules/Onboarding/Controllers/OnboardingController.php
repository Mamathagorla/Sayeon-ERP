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
use App\Modules\Onboarding\Models\EvaluationModel;
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
        // With no bucket chosen, include every linear stage and the two
        // terminal outcomes; the simplified status buckets omit them.
        $requestedBuckets = array_intersect((array) ($getFilters['status'] ?? []), array_keys(OnboardingRecordModel::SIMPLE_BUCKETS));

        $filters = $getFilters;
        $filters['status'] = $requestedBuckets !== []
            ? array_unique(array_merge(...array_map(static fn (string $b) => OnboardingRecordModel::SIMPLE_BUCKETS[$b], $requestedBuckets)))
            : array_merge(OnboardingRecordModel::STATUSES, ['selected', OnboardingRecordModel::REJECTED, OnboardingRecordModel::WITHDRAWN]);

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
        $departmentId = (int) $this->request->getPost('department_id');
        $managerId = (int) $this->request->getPost('reporting_manager_id');

        if ($this->outOfScope(self::COMPANY_SCOPED_ROLES, $companyId)) {
            return redirect()->back()->withInput()->with('error', 'You can only add candidates to your own company.');
        }
        if ($this->request->getPost('candidate_phone') === '' || $departmentId < 1 || $managerId < 1 || trim((string) $this->request->getPost('designation')) === '') {
            return redirect()->back()->withInput()->with('error', 'Enter the candidate phone, applied role, company, department and reporting manager.');
        }

        $department = $this->departmentModel->find($departmentId);
        if ($department === null) {
            return redirect()->back()->withInput()->with('error', 'Select a valid department.');
        }
        $managerBelongsToCompany = db_connect()->table('employee_profiles')
            ->where('user_id', $managerId)
            ->where('company_id', $companyId)
            ->countAllResults() > 0;
        if (! $managerBelongsToCompany) {
            return redirect()->back()->withInput()->with('error', 'Select a reporting manager from the candidate\'s company.');
        }

        $id = $this->recordModel->insert([
            'company_id'           => $companyId,
            'candidate_name'       => $this->request->getPost('candidate_name'),
            'candidate_email'      => $this->request->getPost('candidate_email'),
            'candidate_phone'      => $this->request->getPost('candidate_phone') ?: null,
            'candidate_source'     => $this->request->getPost('candidate_source') ?: 'direct_hire',
            'source_detail'        => $this->request->getPost('source_detail') ?: null,
            'department_id'        => $departmentId,
            'designation'          => $this->request->getPost('designation') ?: null,
            'job_description'      => trim((string) $this->request->getPost('job_description')) ?: null,
            'reporting_manager_id' => $managerId,
            'status'               => 'candidate',
            'created_by'           => $this->currentUserId(),
        ]);

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

        if (($templated = $this->templatedDocument('offer_letter', $id, true)) !== null) {
            return $templated;
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

        if (($templated = $this->templatedDocument('offer_letter', $id, false)) !== null) {
            return $templated;
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
        if ($record['joined_at'] === null && $record['joining_letter_sent_at'] === null) {
            return redirect()->to('/hr/onboarding/' . $id)->with('error', 'The joining letter is available once it has been sent to the candidate.');
        }

        if (($templated = $this->templatedDocument('joining_letter', $id, true)) !== null) {
            return $templated;
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
        if ($record['joined_at'] === null && $record['joining_letter_sent_at'] === null) {
            return redirect()->to('/hr/onboarding/' . $id)->with('error', 'The joining letter is available once it has been sent to the candidate.');
        }

        if (($templated = $this->templatedDocument('joining_letter', $id, false)) !== null) {
            return $templated;
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

        $managerId = (int) $this->request->getPost('reporting_manager_id');
        if ($managerId < 1 || db_connect()->table('employee_profiles')
            ->where('user_id', $managerId)
            ->where('company_id', (int) $record['company_id'])
            ->countAllResults() === 0) {
            return redirect()->back()->withInput()->with('error', 'Select a reporting manager from the candidate\'s company.');
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
            'job_description'      => trim((string) $this->request->getPost('job_description')) ?: null,
            'offered_ctc'          => $this->request->getPost('offered_ctc') ?: null,
            'reporting_manager_id' => $this->request->getPost('reporting_manager_id') ?: null,
            'bgv_status'           => $this->request->getPost('bgv_status') ?: $record['bgv_status'],
            'bgv_notes'            => $this->request->getPost('bgv_notes') ?: null,
            'joining_date'         => $joiningDate,
            'probation_end_date'   => $this->request->getPost('probation_end_date') ?: null,
            'address_line1'        => $this->request->getPost('address_line1') ?: null,
            'address_line2'        => $this->request->getPost('address_line2') ?: null,
            'city'                 => $this->request->getPost('city') ?: null,
            'state'                => $this->request->getPost('state') ?: null,
            'postal_code'          => $this->request->getPost('postal_code') ?: null,
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

        $status = (string) $this->request->getPost('status');
        $allowedNext = [
            'offer_accepted' => 'documents_pending',
            'selected' => 'documents_pending',
            'documents_pending' => 'bgv',
            'bgv' => 'hr_verification',
            'hr_verification' => 'ready_to_join',
            'ready_to_join' => 'joining',
            'joining' => 'joined',
            'joined' => 'induction',
            'induction' => 'probation',
            'probation' => 'confirmed',
        ];
        if (($allowedNext[$record['status']] ?? null) !== $status) {
            return redirect()->to('/hr/onboarding/' . $id)->with('error', 'Use the next available workflow action for this candidate.');
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
     * Stages from which a candidate counts as a hire and can be converted
     * into an employee_profiles row (see createEmployee()).
     */
    public const EMPLOYEE_ELIGIBLE_STATUSES = ['joined', 'induction', 'probation', 'confirmed'];

    /**
     * "Create Employee Record" — converts a completed onboarding into an
     * employee_profiles row in one idempotent step:
     *   - an existing users row with the candidate's email is reused (never
     *     duplicated); otherwise one is created with the standard
     *     'employee' role, same fields UserController::store() writes;
     *   - an existing employee_profiles row for that user is linked
     *     instead of creating a second one (and must be in the same
     *     company — a cross-company match is refused, not moved);
     *   - company_id always comes from the onboarding record, never the
     *     request. Re-submitting once linked is a no-op.
     */
    public function createEmployee(int $id)
    {
        $record = $this->recordModel->find($id);

        if ($record === null || $this->outOfScope(self::COMPANY_SCOPED_ROLES, $record['company_id'])) {
            return redirect()->to('/hr/onboarding')->with('error', 'Onboarding record not found.');
        }

        $back = '/hr/onboarding/' . $id;

        if (! $this->isOwner() || ! can('employee.create')) {
            return redirect()->to($back)->with('error', 'Only HR can create an employee record.');
        }

        if (! empty($record['employee_profile_id'])) {
            return redirect()->to($back)->with('success', 'An employee record is already linked to this candidate.');
        }

        if (! in_array($record['status'], self::EMPLOYEE_ELIGIBLE_STATUSES, true)) {
            return redirect()->to($back)->with('error', 'The employee record can be created once the candidate has joined.');
        }

        $db   = db_connect();
        $db->transStart();

        // Lock the onboarding row so a double-click / second tab can't
        // run this twice and race past the check above.
        $locked = $db->query('SELECT employee_profile_id FROM onboarding_records WHERE id = ? FOR UPDATE', [$id])->getRowArray();
        if (! empty($locked['employee_profile_id'])) {
            $db->transComplete();

            return redirect()->to($back)->with('success', 'An employee record is already linked to this candidate.');
        }

        $email        = strtolower(trim((string) $record['candidate_email']));
        $user         = $this->userModel->withDeleted()->where('email', $email)->first();
        $tempPassword = null;
        $userCreated  = false;

        if ($user !== null && $user['deleted_at'] !== null) {
            $db->transRollback();

            return redirect()->to($back)->with('error', 'A deactivated/deleted user already uses this email. Restore or change it before creating the employee.');
        }

        if ($user === null) {
            $role = $db->table('roles')->select('id')->where('slug', 'employee')->get()->getRowArray();
            if ($role === null) {
                $db->transRollback();

                return redirect()->to($back)->with('error', 'The standard Employee role is missing; create the user manually first.');
            }

            $tempPassword = $this->generateTempPassword();
            $userId = $this->userModel->insert([
                'name'          => $record['candidate_name'],
                'email'         => $record['candidate_email'],
                'phone'         => $record['candidate_phone'] ?: null,
                'role_id'       => (int) $role['id'],
                'status'        => 'active',
                'password_hash' => password_hash($tempPassword, PASSWORD_DEFAULT),
            ]);

            if ($userId === false) {
                $db->transRollback();

                return redirect()->to($back)->with('errors', $this->userModel->errors());
            }
            $userCreated = true;
            $this->logActivity('users', 'create', (int) $userId, 'Created user ' . $record['candidate_email'] . ' from onboarding #' . $id);
        } else {
            $userId = (int) $user['id'];
        }

        $existing = $this->employeeModel->where('user_id', $userId)->first();

        if ($existing !== null) {
            if ((int) $existing['company_id'] !== (int) $record['company_id']) {
                $db->transRollback();

                return redirect()->to($back)->with('error', 'This email already belongs to an employee in another company; it cannot be linked here.');
            }
            $profileId = (int) $existing['id'];
            $message   = 'Linked to the existing employee record ' . $existing['employee_code'] . '.';
        } else {
            $address = implode(', ', array_filter([$record['address_line1'], $record['address_line2'], $record['city'], $record['state'], $record['postal_code'], $record['country']]));

            $profileId = $this->employeeModel->insert([
                'user_id'              => $userId,
                'company_id'           => (int) $record['company_id'],
                'department_id'        => $record['department_id'] ?: null,
                'employee_code'        => $this->employeeModel->nextEmployeeCode((int) $record['company_id']),
                'designation'          => $record['designation'] ?: null,
                'reporting_manager_id' => $record['reporting_manager_id'] ?: null,
                'employment_type'      => 'full_time',
                'status'               => 'active',
                'date_of_joining'      => $record['joining_date'] ?: ($record['joined_at'] ? substr($record['joined_at'], 0, 10) : null),
                'date_of_birth'        => $record['date_of_birth'] ?: null,
                'address'              => $address !== '' ? mb_substr($address, 0, 255) : null,
            ]);

            if ($profileId === false) {
                $db->transRollback();

                return redirect()->to($back)->with('errors', $this->employeeModel->errors());
            }
            $profileId = (int) $profileId;
            $this->logActivity('employee', 'create', $profileId, 'Created employee profile #' . $profileId . ' from onboarding #' . $id);
            $message = 'Employee record created.';
        }

        $this->recordModel->skipValidation(true)->update($id, ['employee_profile_id' => $profileId]);
        $this->logActivity('onboarding', 'update', $id, 'Created/linked employee record #' . $profileId . ' for ' . $record['candidate_name']);

        $db->transComplete();

        if (! $db->transStatus()) {
            return redirect()->to($back)->with('error', 'Could not create the employee record. Nothing was saved.');
        }

        if ($userCreated) {
            $message .= ' A login was created for ' . $record['candidate_email'] . ' (Employee role). Temporary password: ' . $tempPassword . ' — share it securely; it is shown only once.';
        }

        return redirect()->to('/hr/employees/' . $profileId)->with('success', $message);
    }

    private function generateTempPassword(): string
    {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
        $out   = '';
        for ($i = 0; $i < 10; $i++) {
            $out .= $chars[random_int(0, strlen($chars) - 1)];
        }

        return $out . '@' . random_int(10, 99);
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
        // Once the offer is out the candidate's 7-day link may expire before they answer: HR can issue a fresh link
        // without moving the candidate backwards (stage and verification stay as they are).
        $offerOut = $record['status'] === 'offer_sent' && $record['offer_response'] === null;

        if (! $offerOut && ! in_array($record['status'], ['selected_for_onboarding', 'selected', 'link_sent'], true)) {
            return redirect()->to('/hr/onboarding/' . $id)->with('error', 'Select the candidate before sending the onboarding link.');
        }

        $token     = $this->recordModel->issueToken($id);
        if (! $offerOut) {
            $this->recordModel->update($id, ['status' => 'link_sent', 'verification_status' => 'pending']);
        }
        $portalUrl = site_url("onboarding/portal/{$id}/{$token}");

        $this->logActivity('onboarding', 'update', $id, 'Generated a new onboarding portal link for ' . $record['candidate_name']);

        $sent = $this->attemptEmail(
            $record['candidate_email'],
            'Your Onboarding — ' . $record['candidate_name'],
                "Hello {$record['candidate_name']},\n\nPlease use the secure link below to complete your onboarding details:\n\n{$portalUrl}\n\nThis link is valid for 7 days.",
            (int) $record['company_id']
        );

        $message = $sent
            ? 'Portal link generated and emailed to ' . $record['candidate_email'] . '.'
            : 'Portal link generated (shown below to copy/share — email delivery is not configured on this server, so it was not sent automatically).';

        return redirect()->to('/hr/onboarding/' . $id)->with('success', $message);
    }

    public function selectCandidate(int $id)
    {
        $record = $this->recordModel->find($id);
        if ($record === null || $this->outOfScope(self::COMPANY_SCOPED_ROLES, $record['company_id'])) {
            return redirect()->to('/hr/onboarding')->with('error', 'Onboarding record not found.');
        }
        if (! $this->isOwner() || $record['status'] !== 'candidate') {
            return redirect()->to('/hr/onboarding/' . $id)->with('error', 'This candidate cannot be selected from the current stage.');
        }

        $this->recordModel->update($id, ['status' => 'selected_for_onboarding']);
        $this->logActivity('onboarding', 'update', $id, 'Selected ' . $record['candidate_name'] . ' for onboarding');

        return redirect()->to('/hr/onboarding/' . $id)->with('success', 'Candidate selected. Send the secure onboarding link next.');
    }

    public function startReview(int $id)
    {
        $record = $this->recordModel->find($id);
        if ($record === null || $this->outOfScope(self::COMPANY_SCOPED_ROLES, $record['company_id'])) {
            return redirect()->to('/hr/onboarding')->with('error', 'Onboarding record not found.');
        }
        if (! $this->isOwner() || $record['status'] !== 'details_submitted') {
            return redirect()->to('/hr/onboarding/' . $id)->with('error', 'Candidate details are not ready for HR review.');
        }

        $this->recordModel->update($id, ['status' => 'hr_review']);
        $this->logActivity('onboarding', 'update', $id, 'Started HR review for ' . $record['candidate_name']);

        return redirect()->to('/hr/onboarding/' . $id . '/candidate-details')->with('success', 'Candidate moved to HR Review. Review the submission, record your evaluation, then decide.');
    }

    /**
     * Sends the offer letter to the approved candidate through their secure
     * link. The letter is produced (and archived as the issued copy) before
     * anything is sent, so a missing template or missing offer details
     * (joining date, CTC) stop here with guidance instead of the candidate
     * receiving an offer with nothing to read. Also used to resend: while
     * the candidate has not answered, posting again refreshes an expired
     * link and re-sends the mail without touching the original sent date.
     */
    public function sendOffer(int $id)
    {
        $record = $this->recordModel->find($id);
        if ($record === null || $this->outOfScope(self::COMPANY_SCOPED_ROLES, $record['company_id'])) {
            return redirect()->to('/hr/onboarding')->with('error', 'Onboarding record not found.');
        }

        $back     = '/hr/onboarding/' . $id;
        $isResend = $record['status'] === 'offer_sent' && $record['offer_response'] === null;
        if (! $this->isOwner() || ($record['status'] !== 'approved' && ! $isResend)) {
            return redirect()->to($back)->with('error', $record['offer_sent_at'] !== null
                ? 'The candidate has already responded to this offer.'
                : 'Approve the candidate before sending an offer.');
        }
        if (($blocked = $this->letterNotReady('offer_letter', $id, $back)) !== null) {
            return $blocked;
        }

        $token     = $this->liveToken($record);
        $portalUrl = site_url("onboarding/portal/{$id}/{$token}");

        if (! $isResend) {
            $this->recordModel->update($id, ['status' => 'offer_sent', 'offer_sent_at' => date('Y-m-d H:i:s')]);
        }
        $company = $this->companyModel->find($record['company_id'])['name'] ?? 'your employer';
        $sent    = $this->attemptEmail(
            $record['candidate_email'],
            'Your offer letter from ' . $company,
            "Hello {$record['candidate_name']},\n\nCongratulations! Your offer letter from {$company} is ready. Please open the secure link below to read it and accept or decline:\n\n{$portalUrl}\n\nThe link stays valid for at least 7 days.",
            (int) $record['company_id']
        );
        $this->logActivity('onboarding', 'update', $id, ($isResend ? 'Offer letter link re-sent to ' : 'Offer letter sent to ') . $record['candidate_name']);

        return redirect()->to($back)->with('success', ($isResend ? 'Offer letter link re-sent' : 'Offer letter sent') . ($sent
            ? ' to ' . $record['candidate_email'] . '.'
            : '. Email is not configured on this server, so copy the candidate link from the Offer tab and share it.'));
    }

    /**
     * After the candidate has accepted the offer, HR sends the joining
     * letter the same way. The letter is generated first (so a missing
     * template or joining date stops it here), then the candidate sees it
     * in their portal. Safe to repeat: a resend re-mails the link and keeps
     * the first sent date.
     */
    public function sendJoiningLetter(int $id)
    {
        $record = $this->recordModel->find($id);
        if ($record === null || $this->outOfScope(self::COMPANY_SCOPED_ROLES, $record['company_id'])) {
            return redirect()->to('/hr/onboarding')->with('error', 'Onboarding record not found.');
        }

        $back = '/hr/onboarding/' . $id;
        if (! $this->isOwner()) {
            return redirect()->to($back)->with('error', 'Only HR can send the joining letter.');
        }
        if (! $this->offerAccepted($record)) {
            return redirect()->to($back)->with('error', 'The joining letter can be sent once the candidate has accepted the offer.');
        }
        if (($blocked = $this->letterNotReady('joining_letter', $id, $back)) !== null) {
            return $blocked;
        }

        $token     = $this->liveToken($record);
        $portalUrl = site_url("onboarding/portal/{$id}/{$token}");
        $firstSend = $record['joining_letter_sent_at'] === null;

        if ($firstSend) {
            $this->recordModel->update($id, ['joining_letter_sent_at' => date('Y-m-d H:i:s')]);
        }
        $company = $this->companyModel->find($record['company_id'])['name'] ?? 'your employer';
        $sent    = $this->attemptEmail(
            $record['candidate_email'],
            'Your joining letter from ' . $company,
            "Hello {$record['candidate_name']},\n\nThank you for accepting our offer. Your joining letter from {$company} is ready. You can view and download it from your secure candidate link:\n\n{$portalUrl}\n\nThe link stays valid for at least 7 days.",
            (int) $record['company_id']
        );
        $this->logActivity('onboarding', 'update', $id, ($firstSend ? 'Joining letter sent to ' : 'Joining letter link re-sent to ') . $record['candidate_name']);

        return redirect()->to($back)->with('success', ($firstSend ? 'Joining letter sent' : 'Joining letter link re-sent') . ($sent
            ? ' to ' . $record['candidate_email'] . '.'
            : '. Email is not configured on this server, so copy the candidate link from the Offer tab and share it.'));
    }

    /** True once the candidate has accepted the offer (the joining letter follows the acceptance, never precedes it). */
    private function offerAccepted(array $record): bool
    {
        $stages = array_slice(OnboardingRecordModel::STATUSES, (int) array_search('offer_accepted', OnboardingRecordModel::STATUSES, true));

        return $record['offer_accepted_at'] !== null && in_array($record['status'], $stages, true);
    }

    /**
     * Produces the letter now (archiving it as the issued copy). Returns null
     * when it can be produced, otherwise a redirect carrying the reason - a
     * missing template, or the missing details with the usual "complete
     * these fields" guidance.
     */
    private function letterNotReady(string $type, int $id, string $back)
    {
        $svc = new \App\Modules\Document\Services\TemplatedDocument();
        $doc = $svc->forRecord($type, $id, $this->currentUserId());

        if ($doc['status'] === $svc::OK) {
            return null;
        }
        // Send Offer / Send Joining Letter are POST-only, so "Return & continue" must lead to the record page, not to the action URL.
        $svc::flashMissing($doc, site_url(ltrim($back, '/')));

        return redirect()->to($back)->with('error', $doc['message']);
    }

    /** The candidate's token, re-issued or extended so the link they are about to be mailed really works. */
    private function liveToken(array $record): string
    {
        if ($record['candidate_token'] === null) {
            return $this->recordModel->issueToken((int) $record['id']);
        }
        if ($record['token_expires_at'] === null || $record['token_expires_at'] < date('Y-m-d H:i:s', strtotime('+3 days'))) {
            $this->recordModel->update((int) $record['id'], ['token_expires_at' => date('Y-m-d H:i:s', strtotime('+7 days'))]);
        }

        return $record['candidate_token'];
    }

    public function withdraw(int $id)
    {
        $record = $this->recordModel->find($id);
        if ($record === null || $this->outOfScope(self::COMPANY_SCOPED_ROLES, $record['company_id'])) {
            return redirect()->to('/hr/onboarding')->with('error', 'Onboarding record not found.');
        }
        if (! $this->isOwner()) {
            return redirect()->to('/hr/onboarding/' . $id)->with('error', 'Only HR can withdraw this candidate.');
        }
        if (in_array($record['status'], ['withdrawn', 'rejected', 'confirmed'], true)) {
            return redirect()->to('/hr/onboarding/' . $id)->with('error', 'This candidate is already at a closed stage.');
        }

        $this->recordModel->update($id, ['status' => OnboardingRecordModel::WITHDRAWN]);
        $this->logActivity('onboarding', 'update', $id, 'HR withdrew candidate: ' . $record['candidate_name']);

        return redirect()->to('/hr/onboarding/' . $id)->with('success', 'Candidate withdrawn.');
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
        if ($record['status'] !== 'hr_review') {
            return redirect()->to('/hr/onboarding/' . $id)->with('error', 'Move the submitted candidate to HR Review first.');
        }

        $decision = $this->request->getPost('decision');
        if (! in_array($decision, ['approved', 'rejected', 'correction_needed'], true)) {
            return redirect()->to('/hr/onboarding/' . $id)->with('error', 'Invalid decision.');
        }

        $details = '/hr/onboarding/' . $id . '/candidate-details';
        $note    = trim((string) $this->request->getPost('notes'));

        // Changes and rejections must say why: the candidate is shown the
        // change request on their own link, and a rejection needs a reason on file.
        if ($decision !== 'approved' && mb_strlen($note) < 5) {
            return redirect()->to($details)->with('error', $decision === 'correction_needed'
                ? 'Describe what the candidate needs to correct - it is shown to them on their link.'
                : 'Enter the reason for rejecting this candidate.');
        }
        if (mb_strlen($note) > 2000) {
            return redirect()->to($details)->with('error', 'The note must be at most 2000 characters.');
        }

        // A candidate who used the detailed form must be assessed before approval.
        // (Short submissions from before that form existed carry no profile and are exempt.)
        if ($decision === 'approved') {
            $profile = db_connect()->table('onboarding_candidate_profiles')->where('onboarding_record_id', $id)->get()->getRowArray();
            if (! empty($profile['submitted_at']) && ! (new EvaluationModel())->hasAssessment($id)) {
                return redirect()->to($details)->with('error', 'Record your evaluation of the candidate before approving.');
            }
        }

        $data = [
            'verification_status'       => $decision === 'approved' ? 'verified' : 'correction_needed',
            'verification_reviewed_by'  => $this->currentUserId(),
            'verification_reviewed_at'  => date('Y-m-d H:i:s'),
        ];

        if ($decision !== 'approved') {
            $data['verification_notes'] = $note;
        }
        $data['status'] = $decision === 'approved' ? 'approved' : ($decision === 'rejected' ? 'rejected' : 'link_sent');

        $emailed = false;
        if ($decision === 'correction_needed') {
            // The request travels over the existing candidate link: keep it alive (or issue one if
            // this candidate never had a link), and mail it when mail is configured.
            $token = $record['candidate_token'];
            if ($token === null) {
                $token = $this->recordModel->issueToken($id);
            } elseif ($record['token_expires_at'] === null || $record['token_expires_at'] < date('Y-m-d H:i:s', strtotime('+3 days'))) {
                $data['token_expires_at'] = date('Y-m-d H:i:s', strtotime('+7 days'));
            }
        }

        $this->recordModel->update($id, $data);

        if ($decision === 'correction_needed') {
            $emailed = $this->attemptEmail(
                $record['candidate_email'],
                'Changes requested on your onboarding details',
                "Hello {$record['candidate_name']},\n\nHR has reviewed your details and needs a few changes:\n\n{$note}\n\nPlease update and resubmit using your secure link:\n\n"
                    . site_url("onboarding/portal/{$id}/{$token}")
                    . "\n\nThe link stays valid for at least 7 days.",
                (int) $record['company_id']
            );
        }

        $this->logActivity(
            'onboarding',
            'update',
            $id,
            ($decision === 'approved' ? 'Approved' : ($decision === 'rejected' ? 'Rejected' : 'Requested changes on')) . ' candidate-submitted information for ' . $record['candidate_name']
                . ($note !== '' ? ': ' . mb_substr($note, 0, 150) : '')
        );

        return redirect()->to('/hr/onboarding/' . $id)->with('success', match ($decision) {
            'approved' => 'Candidate approved. Send Offer is now available.',
            'rejected' => 'Candidate rejected.',
            default => $emailed
                ? 'Changes requested - the candidate was emailed and can update and resubmit through their link.'
                : 'Changes requested - the candidate will see them on their link and can update and resubmit (email is not configured, so share the link from the Offer tab if needed).',
        });
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
    private function attemptEmail(string $to, string $subject, string $message, ?int $companyId = null): bool
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
            // Candidates know the company, not the ERP: show the company as the sender (the sending address stays the configured one).
            $companyName = $companyId !== null ? trim((string) ($this->companyModel->find($companyId)['name'] ?? '')) : '';
            if ($companyName !== '' && $config->fromEmail !== '') {
                $email->setFrom($config->fromEmail, $companyName);
            }
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
            if (! $this->isOwner()) {
                return redirect()->to('/hr/onboarding/' . $id)->with('error', 'Only HR can remove this candidate.');
            }

            // The candidate's own uploads (resume, certificates) go with the record - their personal
            // data should not outlive it. Files HR attached themselves are left alone.
            foreach ($this->documentModel->where('onboarding_record_id', $id)->where('uploaded_by', null)->findAll() as $document) {
                if (str_starts_with((string) $document['file_path'], \App\Modules\Onboarding\Services\CandidateFormService::STORAGE_FOLDER . '/')) {
                    service('fileStorage')->delete($document['file_path']);
                    $this->documentModel->delete($document['id']);
                }
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
            'title'       => $record ? 'Edit Candidate' : 'Add Candidate',
            'navActive'   => 'hr-onboarding',
            'record'      => $record,
            'companies'   => $this->scopedCompanyOptions($this->companyModel->optionsList()),
            'departments' => $this->departmentModel->optionsList(),
            'managers'    => $this->scopedUserOptions($this->userModel->listForOptionsWithCompany()),
            'bgvStatuses' => OnboardingRecordModel::BGV_STATUSES,
            'candidateSources' => [
                'job_application' => 'Job Application',
                'referral' => 'Referral',
                'agency' => 'Agency',
                'direct_hire' => 'Direct Hiring',
            ],
        ];
    }
}
