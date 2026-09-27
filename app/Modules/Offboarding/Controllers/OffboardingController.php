<?php

namespace App\Modules\Offboarding\Controllers;

use App\Controllers\BaseController;
use App\Modules\Company\Models\CompanyModel;
use App\Modules\Document\Models\DocumentModel;
use App\Modules\HR\Models\EmployeeProfileModel;
use App\Modules\Notification\Services\NotificationService;
use App\Modules\Offboarding\Models\OffboardingRecordModel;
use App\Modules\Offboarding\Models\OffboardingTaskModel;
use Dompdf\Dompdf;
use Dompdf\Options;

class OffboardingController extends BaseController
{
    /**
     * Roles allowed to control the main record (overall exit status,
     * exit details) — HR is the primary owner per spec; Manager/
     * Company Admin/Accountant/Employee hold offboarding.edit too, but
     * only to complete their own checklist tasks (see
     * OffboardingTaskController), never the record or its overall
     * status. Same layered pattern as Onboarding's OWNER_ROLES /
     * LeaveController::ROLE_RANK.
     */
    private const OWNER_ROLES = ['hr', 'super_admin'];

    protected OffboardingRecordModel $recordModel;
    protected OffboardingTaskModel $taskModel;
    protected EmployeeProfileModel $employeeModel;
    protected CompanyModel $companyModel;
    protected DocumentModel $documentModel;
    protected NotificationService $notificationService;

    public function __construct()
    {
        $this->recordModel         = new OffboardingRecordModel();
        $this->taskModel           = new OffboardingTaskModel();
        $this->employeeModel       = new EmployeeProfileModel();
        $this->companyModel        = new CompanyModel();
        $this->documentModel       = new DocumentModel();
        $this->notificationService = new NotificationService();
    }

    public function index()
    {
        $roleSlug = session('roleSlug');

        // An Employee only ever sees their own exit record(s), never the
        // company-wide pipeline — unlike Manager/Admin/Accountant/HR.
        if ($roleSlug === 'employee') {
            $myProfile = $this->employeeModel->byUserId((int) $this->currentUserId());
            $records   = $myProfile ? $this->recordModel->filtered(['employee_user_id' => $this->currentUserId()])->findAll() : [];

            return view('App\Modules\Offboarding\index', [
                'title'       => 'My Offboarding',
                'navActive'   => 'hr-offboarding',
                'records'     => $records,
                'stageCounts' => [],
                'stages'      => array_merge(OffboardingRecordModel::STATUSES, [OffboardingRecordModel::RESCINDED]),
                'stageLabels' => OffboardingRecordModel::STAGE_LABELS,
                'isOwner'     => false,
                'isEmployee'  => true,
                'hasActiveExit' => $myProfile ? $this->recordModel->hasActiveExit($myProfile['id']) : false,
                'filters'     => [],
            ]);
        }

        $filters = array_filter($this->request->getGet(['status', 'exit_type']) ?? []);

        if (! in_array($filters['exit_type'] ?? '', OffboardingRecordModel::EXIT_TYPES, true)) {
            unset($filters['exit_type']);
        }
        // "stage" is the list page's summary bucket (pending/progress/
        // completed) — exposed to the view under the same key.
        $stage = $this->request->getGet('stage');
        if (isset(OffboardingRecordModel::STAGE_GROUPS[$stage])) {
            $filters['stage_group'] = $stage;
        }

        $companyScope = $this->companyScopeFor(self::COMPANY_SCOPED_ROLES);
        if ($companyScope !== null) {
            $filters['company_id'] = $companyScope;
        }

        return view('App\Modules\Offboarding\index', [
            'title'         => 'Offboarding',
            'navActive'     => 'hr-offboarding',
            'records'       => $this->recordModel->filtered($filters)->findAll(),
            'stageCounts'   => $this->recordModel->countsByStatus($companyScope),
            'stages'        => array_merge(OffboardingRecordModel::STATUSES, [OffboardingRecordModel::RESCINDED]),
            'stageLabels'   => OffboardingRecordModel::STAGE_LABELS,
            'isOwner'       => $this->isOwner(),
            'isEmployee'    => false,
            'hasActiveExit' => false,
            'filters'       => $filters,
        ]);
    }

    public function create()
    {
        $roleSlug = session('roleSlug');

        if ($roleSlug === 'employee') {
            $myProfile = $this->employeeModel->byUserId((int) $this->currentUserId());

            if ($myProfile === null) {
                return redirect()->to('/hr/offboarding')->with('error', 'No employee profile found for your account.');
            }
            if ($this->recordModel->hasActiveExit($myProfile['id'])) {
                return redirect()->to('/hr/offboarding')->with('error', 'You already have an exit in progress.');
            }

            return view('App\Modules\Offboarding\form', [
                'title'      => 'Submit Resignation',
                'navActive'  => 'hr-offboarding',
                'record'     => null,
                'isEmployee' => true,
                'myProfile'  => $myProfile,
            ]);
        }

        if (! $this->isOwner()) {
            return redirect()->to('/hr/offboarding')->with('error', 'Only HR can initiate an exit.');
        }

        $companyScope = $this->companyScopeFor(self::COMPANY_SCOPED_ROLES);
        $employees    = $this->employeeModel->filtered($companyScope !== null ? ['company_id' => $companyScope] : [])
            ->where('employee_profiles.status', 'active')
            ->findAll();

        return view('App\Modules\Offboarding\form', [
            'title'      => 'Initiate Exit',
            'navActive'  => 'hr-offboarding',
            'record'     => null,
            'isEmployee' => false,
            'employees'  => $employees,
        ]);
    }

    public function store()
    {
        $roleSlug = session('roleSlug');

        if ($roleSlug === 'employee') {
            $myProfile = $this->employeeModel->byUserId((int) $this->currentUserId());

            if ($myProfile === null || $this->recordModel->hasActiveExit($myProfile['id'])) {
                return redirect()->to('/hr/offboarding')->with('error', 'You cannot submit a resignation right now.');
            }

            $rules = $this->recordModel->getValidationRules();
            unset($rules['employee_profile_id'], $rules['company_id']);
            $rules['exit_type'] = 'permit_empty'; // Employees only ever resign — locked below, not user-submitted.

            if (! $this->validate($rules)) {
                return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
            }

            $id = $this->recordModel->insert([
                'employee_profile_id' => $myProfile['id'],
                'company_id'          => $myProfile['company_id'],
                'exit_type'           => 'resignation',
                'reason'              => $this->request->getPost('reason') ?: null,
                'exit_date'           => $this->request->getPost('exit_date'),
                'last_working_day'    => $this->request->getPost('last_working_day') ?: null,
                'notice_period_days'  => $this->request->getPost('notice_period_days') ?: null,
                'initiated_by'        => $this->currentUserId(),
            ]);

            $this->taskModel->seedDefaultTasks($id);
            $this->logActivity('offboarding', 'create', $id, 'Submitted resignation');
            $this->notifyOnCreate($id, $myProfile);

            return redirect()->to('/hr/offboarding/' . $id)->with('success', 'Resignation submitted.');
        }

        if (! $this->isOwner()) {
            return redirect()->to('/hr/offboarding')->with('error', 'Only HR can initiate an exit.');
        }

        // company_id is never submitted by the form — it's derived below
        // from the selected employee's own employee_profiles.company_id,
        // never trusted from the browser. Validating the model's
        // unmodified rule set here (which requires company_id) is what
        // was throwing "The company_id field is required."
        $rules = $this->recordModel->getValidationRules();
        unset($rules['company_id']);

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $employeeProfileId = (int) $this->request->getPost('employee_profile_id');
        $employee = $this->employeeModel->withRelations($employeeProfileId);

        if ($employee === null || $this->outOfScope(self::COMPANY_SCOPED_ROLES, $employee['company_id'])) {
            return redirect()->back()->withInput()->with('error', 'Select a valid employee from your own company.');
        }

        if ($this->recordModel->hasActiveExit($employeeProfileId)) {
            return redirect()->back()->withInput()->with('error', 'This employee already has an exit in progress.');
        }

        $id = $this->recordModel->insert([
            'employee_profile_id' => $employeeProfileId,
            'company_id'          => $employee['company_id'],
            'exit_type'           => $this->request->getPost('exit_type'),
            'reason'              => $this->request->getPost('reason') ?: null,
            'exit_date'           => $this->request->getPost('exit_date'),
            'last_working_day'    => $this->request->getPost('last_working_day') ?: null,
            'notice_period_days'  => $this->request->getPost('notice_period_days') ?: null,
            'initiated_by'        => $this->currentUserId(),
        ]);

        $this->taskModel->seedDefaultTasks($id);
        $this->logActivity('offboarding', 'create', $id, 'Initiated ' . $this->request->getPost('exit_type') . ' for ' . $employee['user_name']);
        $this->notifyOnCreate($id, $employee);

        return redirect()->to('/hr/offboarding/' . $id)->with('success', 'Exit record created.');
    }

    public function show(int $id)
    {
        $record = $this->recordModel->withRelations($id);

        if ($record === null || $this->outOfScope(self::COMPANY_SCOPED_ROLES, $record['company_id'])) {
            return redirect()->to('/hr/offboarding')->with('error', 'Offboarding record not found.');
        }

        // An Employee may only ever view their own exit record.
        if (session('roleSlug') === 'employee' && (int) $record['employee_user_id'] !== (int) $this->currentUserId()) {
            return redirect()->to('/hr/offboarding')->with('error', 'Offboarding record not found.');
        }

        // Grouped by process PHASE (where it sits in the exit), not
        // department — each task still carries its own owner_role.
        // Phases (name/order/active) come from OffboardingPhaseModel
        // (see OffboardingPhaseController), not the old hardcoded PHASES
        // constant, so HR's Add/Edit/Reorder/Delete changes show up here
        // immediately. Inactive phases don't get their own section; any
        // task left with no active phase still shows, under a trailing
        // "Other" — tasks are never dropped from view over this.
        $phaseModel   = new \App\Modules\Offboarding\Models\OffboardingPhaseModel();
        $activePhases = $phaseModel->ordered(true);

        $tasksByPhase = [];
        $exitCompletedPhaseName = null;
        foreach ($activePhases as $p) {
            $tasksByPhase[$p['name']] = [];
            if ($p['legacy_key'] === 'exit_completed') {
                $exitCompletedPhaseName = $p['name'];
            }
        }
        $tasks = $this->taskModel->forRecord($id);
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

        return view('App\Modules\Offboarding\show', [
            'title'           => $record['employee_name'],
            'navActive'       => 'hr-offboarding',
            'record'          => $record,
            'tasksByPhase'    => $tasksByPhase,
            'exitCompletedPhaseName' => $exitCompletedPhaseName,
            'activity'        => session('roleSlug') === 'employee' ? [] : $this->activityFor($id),
            'ownerRoleLabels' => OffboardingTaskModel::OWNER_ROLE_LABELS,
            'stages'          => OffboardingRecordModel::STATUSES,
            'stageLabels'     => OffboardingRecordModel::STAGE_LABELS,
            'nextStatus'      => $this->recordModel->nextStatus($record['status']),
            'documents'       => $this->documentModel->filtered(['employee_user_id' => $record['employee_user_id'], 'category' => 'hr'])->findAll(),
            'isOwner'         => $this->isOwner(),
            'myRole'          => session('roleSlug'),
        ]);
    }

    /**
     * The Activity tab's feed — activity_logs rows already written by
     * logActivity() for this record, newest first, actor name joined in.
     */
    private function activityFor(int $recordId): array
    {
        return db_connect()->table('activity_logs')
            ->select('activity_logs.*, users.name as user_name')
            ->join('users', 'users.id = activity_logs.user_id', 'left')
            ->where('activity_logs.module', 'offboarding')
            ->where('activity_logs.record_id', $recordId)
            ->orderBy('activity_logs.created_at', 'DESC')
            ->get()
            ->getResultArray();
    }

    /**
     * Relieving letter + experience certificate in one document — same
     * standalone printable pattern as OnboardingController::offerLetter()
     * (and ultimately InvoiceController::print()): its own minimal
     * HTML/CSS, a Print button that calls window.print(), no PDF file
     * generated or stored. Staff-only (HR/super_admin), same as the
     * offer letter — available at any stage rather than gated to
     * exit_completed, so HR can preview/prepare it ahead of the
     * employee's actual last working day.
     */
    public function relievingLetter(int $id)
    {
        $record = $this->recordModel->withRelations($id);

        if ($record === null || $this->outOfScope(self::COMPANY_SCOPED_ROLES, $record['company_id'])) {
            return redirect()->to('/hr/offboarding')->with('error', 'Offboarding record not found.');
        }
        if (! $this->isOwner()) {
            return redirect()->to('/hr/offboarding/' . $id)->with('error', 'Only HR can view the relieving letter.');
        }

        return view('App\Modules\Offboarding\relieving_letter', [
            'title'     => 'Relieving Letter — ' . $record['employee_name'],
            'record'    => $record,
            'company'   => $this->companyModel->find($record['company_id']),
            'autoPrint' => $this->request->getGet('print') === '1',
        ]);
    }

    /**
     * Same document as relievingLetter(), rendered to a downloadable PDF
     * via a separate Dompdf-safe export view (table layout, no flex/grid
     * — see PayrollController::payslipPdf() for the same pattern).
     */
    public function relievingLetterPdf(int $id)
    {
        $record = $this->recordModel->withRelations($id);

        if ($record === null || $this->outOfScope(self::COMPANY_SCOPED_ROLES, $record['company_id'])) {
            return redirect()->to('/hr/offboarding')->with('error', 'Offboarding record not found.');
        }
        if (! $this->isOwner()) {
            return redirect()->to('/hr/offboarding/' . $id)->with('error', 'Only HR can download the relieving letter.');
        }

        $options = new Options();
        $options->set('isRemoteEnabled', false);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml(view('App\Modules\Offboarding\exports\relieving_letter_pdf', [
            'record'  => $record,
            'company' => $this->companyModel->find($record['company_id']),
        ]));
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $this->response
            ->setContentType('application/pdf')
            ->setHeader('Content-Disposition', 'attachment; filename="relieving-letter-' . url_title($record['employee_name'], '-', true) . '.pdf"')
            ->setBody($dompdf->output());
    }

    public function edit(int $id)
    {
        $record = $this->recordModel->find($id);

        if ($record === null || $this->outOfScope(self::COMPANY_SCOPED_ROLES, $record['company_id'])) {
            return redirect()->to('/hr/offboarding')->with('error', 'Offboarding record not found.');
        }
        if (! $this->isOwner()) {
            return redirect()->to('/hr/offboarding/' . $id)->with('error', 'Only HR can edit this exit record.');
        }

        return view('App\Modules\Offboarding\form', [
            'title'      => 'Edit Exit Record',
            'navActive'  => 'hr-offboarding',
            'record'     => $record,
            'isEmployee' => false,
        ]);
    }

    public function update(int $id)
    {
        $record = $this->recordModel->find($id);

        if ($record === null || $this->outOfScope(self::COMPANY_SCOPED_ROLES, $record['company_id'])) {
            return redirect()->to('/hr/offboarding')->with('error', 'Offboarding record not found.');
        }
        if (! $this->isOwner()) {
            return redirect()->to('/hr/offboarding/' . $id)->with('error', 'Only HR can edit this exit record.');
        }

        $rules = $this->recordModel->getValidationRules();
        unset($rules['employee_profile_id'], $rules['company_id']);

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $this->recordModel->update($id, [
            'exit_type'          => $this->request->getPost('exit_type'),
            'reason'             => $this->request->getPost('reason') ?: null,
            'exit_date'          => $this->request->getPost('exit_date'),
            'last_working_day'   => $this->request->getPost('last_working_day') ?: null,
            'notice_period_days' => $this->request->getPost('notice_period_days') ?: null,
        ]);

        $this->logActivity('offboarding', 'update', $id, 'Updated exit record #' . $id);

        return redirect()->to('/hr/offboarding/' . $id)->with('success', 'Exit record updated.');
    }

    public function updateStatus(int $id)
    {
        $record = $this->recordModel->find($id);

        if ($record === null || $this->outOfScope(self::COMPANY_SCOPED_ROLES, $record['company_id'])) {
            return redirect()->to('/hr/offboarding')->with('error', 'Offboarding record not found.');
        }
        if (! $this->isOwner()) {
            return redirect()->to('/hr/offboarding/' . $id)->with('error', 'Only HR can change the overall exit status.');
        }

        $status = $this->request->getPost('status');
        $validStatuses = array_merge(OffboardingRecordModel::STATUSES, [OffboardingRecordModel::RESCINDED]);

        if (! in_array($status, $validStatuses, true)) {
            return redirect()->to('/hr/offboarding/' . $id)->with('error', 'Invalid stage.');
        }

        $data = ['status' => $status];

        if ($status === 'hr_approval' && $record['manager_reviewed_at'] === null) {
            $data['manager_reviewed_by'] = $this->currentUserId();
            $data['manager_reviewed_at'] = date('Y-m-d H:i:s');
        }
        if ($status === 'exit_initiated' && $record['hr_approved_at'] === null) {
            $data['hr_approved_by'] = $this->currentUserId();
            $data['hr_approved_at'] = date('Y-m-d H:i:s');
        }
        if ($status === 'exit_completed' && $record['completed_at'] === null) {
            $data['completed_at'] = date('Y-m-d H:i:s');
        }

        $this->recordModel->update($id, $data);

        // The one place an employee's status actually changes — never a
        // delete, and only once the pipeline genuinely reaches its end.
        // Preserves every historical record (payslips, leave, past
        // tasks) exactly as instructed.
        if ($status === 'exit_completed') {
            $this->employeeModel->update($record['employee_profile_id'], [
                'status' => $record['exit_type'] === 'termination' ? 'terminated' : 'resigned',
            ]);
        }

        $employee = $this->employeeModel->withRelations($record['employee_profile_id']);
        $label    = $status === OffboardingRecordModel::RESCINDED ? 'Rescinded' : OffboardingRecordModel::STAGE_LABELS[$status];

        $this->logActivity('offboarding', 'update', $id, 'Moved ' . ($employee['user_name'] ?? 'employee') . '\'s exit to ' . $label);

        if ($employee !== null) {
            $this->notificationService->notify(
                (int) $employee['user_id'],
                'offboarding_status',
                'Exit status update',
                'Your exit process is now at "' . $label . '".',
                'offboarding',
                $id
            );
        }
        if (! empty($employee['reporting_manager_id'])) {
            $this->notificationService->notify(
                (int) $employee['reporting_manager_id'],
                'offboarding_status',
                'Exit update: ' . ($employee['user_name'] ?? ''),
                ($employee['user_name'] ?? 'Employee') . '\'s exit is now at "' . $label . '".',
                'offboarding',
                $id
            );
        }

        return redirect()->to('/hr/offboarding/' . $id)->with('success', 'Stage updated to ' . $label . '.');
    }

    public function destroy(int $id)
    {
        $record = $this->recordModel->find($id);

        if ($record !== null) {
            if ($this->outOfScope(self::COMPANY_SCOPED_ROLES, $record['company_id'])) {
                return redirect()->to('/hr/offboarding')->with('error', 'Offboarding record not found.');
            }
            if (! $this->isOwner()) {
                return redirect()->to('/hr/offboarding/' . $id)->with('error', 'Only HR can remove an exit record.');
            }

            $this->recordModel->delete($id);
            $this->logActivity('offboarding', 'delete', $id, 'Removed exit record #' . $id);
        }

        return redirect()->to('/hr/offboarding')->with('success', 'Exit record removed.');
    }

    private function isOwner(): bool
    {
        return in_array(session('roleSlug'), self::OWNER_ROLES, true);
    }

    private function notifyOnCreate(int $id, array $employee): void
    {
        if (! empty($employee['reporting_manager_id'])) {
            $this->notificationService->notify(
                (int) $employee['reporting_manager_id'],
                'offboarding_review',
                'Exit review needed: ' . ($employee['user_name'] ?? ''),
                ($employee['user_name'] ?? 'An employee') . '\'s exit needs your review.',
                'offboarding',
                $id
            );
        }
    }
}
