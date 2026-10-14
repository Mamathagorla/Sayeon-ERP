<?php

namespace App\Modules\HR\Controllers;

use App\Controllers\BaseController;
use App\Modules\Auth\Models\UserModel;
use App\Modules\Company\Models\CompanyModel;
use App\Modules\Department\Models\DepartmentModel;
use App\Modules\Document\Services\DocumentTypeRegistry;
use App\Modules\HR\Models\EmployeeProfileModel;

class EmployeeController extends BaseController
{
    protected EmployeeProfileModel $profileModel;
    protected UserModel $userModel;
    protected CompanyModel $companyModel;
    protected DepartmentModel $departmentModel;

    public function __construct()
    {
        $this->profileModel    = new EmployeeProfileModel();
        $this->userModel       = new UserModel();
        $this->companyModel    = new CompanyModel();
        $this->departmentModel = new DepartmentModel();
    }

    public function index()
    {
        $filters = array_filter($this->request->getGet(['company_id', 'department_id', 'status']) ?? []);

        $companyScope = $this->companyScopeFor(self::COMPANY_SCOPED_ROLES);
        if ($companyScope !== null) {
            $filters['company_id'] = $companyScope;
        }

        return view('App\Modules\HR\employees/index', [
            'title'     => 'Employees',
            'navActive' => 'hr-employees',
            'employees' => $this->profileModel->filtered($filters)->findAll(),
            'companies' => $this->scopedCompanyOptions($this->companyModel->optionsList()),
            'departments' => $this->departmentModel->optionsList(),
            'statuses'  => EmployeeProfileModel::STATUSES,
            'filters'   => $filters,
        ]);
    }

    public function create()
    {
        return view('App\Modules\HR\employees/form', $this->formData(null));
    }

    public function store()
    {
        if (! $this->validate($this->profileModel->getValidationRules(), $this->profileModel->getValidationMessages())) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $dob = $this->request->getPost('date_of_birth');
        if ($dob && $dob > date('Y-m-d')) {
            return redirect()->back()->withInput()->with('errors', ['Date of birth cannot be in the future.']);
        }

        if ($this->outOfScope(self::COMPANY_SCOPED_ROLES, (int) $this->request->getPost('company_id'))) {
            return redirect()->back()->withInput()->with('error', 'You can only add employees to your own company.');
        }

        if ($this->managerMismatch((int) $this->request->getPost('company_id'))) {
            return redirect()->back()->withInput()->with('error', 'Choose a reporting manager from the same company.');
        }

        $photoError = $this->validatePhoto();
        if ($photoError !== null) {
            return redirect()->back()->withInput()->with('errors', [$photoError]);
        }

        $id = $this->profileModel->insert($this->payload(true));
        $this->savePhoto((int) $this->request->getPost('user_id'));
        $this->logActivity('employee', 'create', $id, 'Created employee profile #' . $id);

        return redirect()->to('/hr/employees/' . $id)->with('success', 'Employee profile created.');
    }

    public function show(int $id)
    {
        $employee = $this->profileModel->withRelations($id);

        if ($employee === null || $this->outOfScope(self::COMPANY_SCOPED_ROLES, $employee['company_id'] ?? null)) {
            return redirect()->to('/hr/employees')->with('error', 'Employee not found.');
        }

        return view('App\Modules\HR\employees/show', [
            'title'     => 'Employee Profile',
            'navActive' => 'hr-employees',
            'employee'  => $employee,
        ] + $this->recordSections($employee));
    }

    /**
     * Everything the profile page shows inline instead of linking out to
     * the Attendance / Leave / Payroll / Performance / Document /
     * Onboarding pages. Each block is loaded only when the viewer holds
     * the permission that page required, so nobody sees data they
     * couldn't reach before. The employee is already company-scope
     * checked by show(). Also builds the KPI cards and the "Pending
     * Actions" list from the same data, so they can never disagree with
     * the sections they summarise. Lists are loaded in full-ish sizes
     * because the tab panels show them all; the Overview tab slices them.
     */
    private function recordSections(array $employee): array
    {
        $userId    = (int) $employee['user_id'];
        $profileId = (int) $employee['id'];
        $today     = date('Y-m-d');
        $num       = static fn (float $n): string => rtrim(rtrim(number_format($n, 1), '0'), '.');
        $money     = static fn (float $n): string => number_format($n, 2);

        $data    = [];
        $metrics = [];
        $actions = [];

        if (can('attendance.view')) {
            $attendance = new \App\Modules\HR\Models\AttendanceModel();

            // This month's days, bucketed so the donut segments never overlap:
            // on time / late (both attended), absent, on approved leave.
            $onTime = $late = $absent = $onLeave = 0;
            foreach ($attendance->filtered(['user_id' => $userId, 'date_from' => date('Y-m-01'), 'date_to' => $today])->findAll() as $r) {
                if (in_array($r['status'], ['present', 'half_day'], true)) {
                    if ($r['check_in'] && date('H:i:s', strtotime($r['check_in'])) > \App\Modules\Dashboard\Controllers\DashboardController::LATE_AFTER) {
                        $late++;
                    } else {
                        $onTime++;
                    }
                } elseif ($r['status'] === 'absent') {
                    $absent++;
                } elseif ($r['status'] === 'on_leave') {
                    $onLeave++;
                }
            }
            $attended = $onTime + $late;
            $pct      = ($attended + $absent) > 0 ? (int) round($attended / ($attended + $absent) * 100) : null;

            $rows = $attendance->filtered(['user_id' => $userId])->findAll(60);

            $data['attendanceMonth'] = ['onTime' => $onTime, 'late' => $late, 'absent' => $absent, 'onLeave' => $onLeave, 'pct' => $pct, 'label' => date('F Y')];
            $data['attendance']      = $rows;

            $metrics[] = ['fa-calendar-check', 'Attendance', $pct !== null ? $pct . '%' : '—', 'This month', 'attendance'];

            $since   = date('Y-m-d', strtotime('-7 days'));
            $missing = count(array_filter($rows, static fn (array $r) => $r['date'] >= $since && $r['date'] < $today && $r['check_in'] && ! $r['check_out']));
            if ($missing > 0) {
                $actions[] = ['warning', 'fa-clock', $missing . ' day' . ($missing > 1 ? 's' : '') . ' with no check-out in the last 7 days'];
            }
        }

        if (can('leave.view')) {
            $leaveModel = new \App\Modules\HR\Models\LeaveRequestModel();
            $balances   = [];
            $quota      = 0.0;
            $used       = 0.0;
            foreach ((new \App\Modules\HR\Models\LeaveTypeModel())->optionsList() as $type) {
                $typeUsed   = $leaveModel->approvedDaysThisYear($userId, (int) $type['id']);
                $balances[] = [
                    'name'      => $type['name'],
                    'quota'     => (float) $type['annual_quota'],
                    'used'      => $typeUsed,
                    'remaining' => max(0, (float) $type['annual_quota'] - $typeUsed),
                ];
                // Unpaid leave types (leave_types.is_paid = 0) have no
                // real quota to run out of — Payroll deducts them as LOP
                // instead (see PayrollController::generate()) — so they
                // don't count towards this "remaining balance" total.
                if ($type['is_paid']) {
                    $quota += (float) $type['annual_quota'];
                    $used  += $typeUsed;
                }
            }

            $requests = $leaveModel->filtered(['user_id' => $userId])->findAll(50);
            $upcoming = array_values(array_filter($requests, static fn (array $l) => $l['end_date'] >= $today && in_array($l['status'], ['approved', 'pending'], true)));
            usort($upcoming, static fn (array $a, array $b) => strcmp($a['start_date'], $b['start_date']));

            $data['leaveBalances'] = $balances;
            $data['leaveTotals']   = ['quota' => $quota, 'used' => $used, 'remaining' => max(0, $quota - $used)];
            $data['leaveRequests'] = $requests;
            $data['upcomingLeave'] = array_slice($upcoming, 0, 4);

            $metrics[] = ['fa-umbrella-beach', 'Leave', $num(max(0, $quota - $used)) . ' days', 'Remaining this year', 'leave'];

            $pending = (new \App\Modules\HR\Models\LeaveRequestModel())->where('user_id', $userId)->where('status', 'pending')->countAllResults();
            if ($pending > 0) {
                $actions[] = ['warning', 'fa-plane-departure', $pending . ' leave request' . ($pending > 1 ? 's' : '') . ' waiting for approval'];
            }
        }

        if (can('performance.view')) {
            $reviews = (new \App\Modules\HR\Models\PerformanceReviewModel())->filtered(['user_id' => $userId])->findAll(20);

            $data['reviews'] = $reviews;

            $rated     = array_values(array_filter($reviews, static fn (array $r) => $r['status'] !== 'draft' && ! empty($r['rating'])));
            $metrics[] = $rated !== []
                ? ['fa-star', 'Performance', (int) $rated[0]['rating'] . ' / 5', 'Last review: ' . $rated[0]['cycle_name'], 'performance']
                : ['fa-star', 'Performance', '—', 'No reviews yet', 'performance'];

            foreach ($reviews as $r) {
                if ($r['status'] === 'submitted') {
                    $actions[] = ['info', 'fa-chart-line', 'Review "' . $r['cycle_name'] . '" is waiting for acknowledgement'];
                } elseif ($r['status'] === 'draft') {
                    $actions[] = ['info', 'fa-chart-line', 'Review "' . $r['cycle_name'] . '" is still a draft'];
                }
            }
        }

        // Kept after Performance so the KPI row reads Attendance / Leave /
        // Performance / Salary, left to right — the order the profile
        // header now shows them in.
        if (can('payroll.edit')) {
            $salary = (new \App\Modules\HR\Models\SalaryStructureModel())->byUserId($userId);

            $data['showSalary'] = true;
            $data['salary']     = $salary;
            $data['payslips']   = array_slice((new \App\Modules\HR\Models\PayslipModel())->forUser($userId), 0, 24);

            if ($salary === null) {
                $metrics[] = ['fa-money-check-dollar', 'Salary', '—', 'Not set up yet', 'payroll'];
                $actions[] = ['warning', 'fa-money-check-dollar', 'No salary structure set up'];
            } else {
                $gross     = (float) $salary['basic'] + (float) $salary['hra'] + (float) $salary['allowances'];
                $metrics[] = ['fa-money-check-dollar', 'Salary', number_format($gross * 12), 'Gross · per annum', 'payroll'];
            }
        }

        // Increment letter history — same viewers who can manage this
        // profile (employee.edit) can issue/see it; a plain employee
        // never reaches this page at all (no employee.view), so no
        // separate self-view branch is needed here the way Onboarding's
        // self-view required one.
        if (can('employee.edit')) {
            $data['showIncrements'] = true;
            $data['increments']     = (new \App\Modules\HR\Models\EmployeeIncrementLetterModel())->forEmployee($profileId);
        }

        // Onboarding / exit records are linked to a profile explicitly
        // (employee_profile_id) — an unlinked candidate simply isn't shown.
        $onboarding = null;
        if (can('onboarding.view')) {
            $onboarding = (new \App\Modules\Onboarding\Models\OnboardingRecordModel())->filtered()
                ->where('onboarding_records.employee_profile_id', $profileId)->first();

            $data['showOnboarding']  = true;
            $data['onboarding']      = $onboarding;
            $data['onboardingTasks'] = $onboarding !== null ? (new \App\Modules\Onboarding\Models\OnboardingTaskModel())->forRecord((int) $onboarding['id']) : [];

            $open = count(array_filter($data['onboardingTasks'], static fn (array $t) => in_array($t['status'], ['pending', 'in_progress'], true)));
            if ($onboarding !== null && $onboarding['status'] !== 'confirmed' && $open > 0) {
                $actions[] = ['info', 'fa-user-plus', $open . ' onboarding task' . ($open > 1 ? 's' : '') . ' still open'];
            }
        }

        $offboarding = null;
        if (can('offboarding.view')) {
            $offboarding = (new \App\Modules\Offboarding\Models\OffboardingRecordModel())->filtered()
                ->where('offboarding_records.employee_profile_id', $profileId)->first();

            $data['showOffboarding']  = true;
            $data['offboarding']      = $offboarding;
            $data['offboardingTasks'] = $offboarding !== null
                ? (new \App\Modules\Offboarding\Models\OffboardingTaskModel())->where('offboarding_record_id', $offboarding['id'])->orderBy('id', 'ASC')->findAll()
                : [];

            if ($offboarding !== null && in_array($offboarding['status'], \App\Modules\Offboarding\Models\OffboardingRecordModel::ACTIVE_STATUSES, true)) {
                $openExit  = count(array_filter($data['offboardingTasks'], static fn (array $t) => in_array($t['status'], ['pending', 'in_progress'], true)));
                $actions[] = ['danger', 'fa-person-walking-arrow-right', 'Exit in progress — ' . (\App\Modules\Offboarding\Models\OffboardingRecordModel::STAGE_LABELS[$offboarding['status']] ?? $offboarding['status']) . ($openExit > 0 ? ' · ' . $openExit . ' task' . ($openExit > 1 ? 's' : '') . ' open' : '')];
            }
        }

        if (can('document.view')) {
            $documents = new \App\Modules\Document\Models\DocumentModel();
            $found     = $documents->filtered(['employee_user_id' => $userId])->findAll();
            if ($onboarding !== null) {
                $found = array_merge($found, $documents->filtered(['onboarding_record_id' => $onboarding['id']])->findAll());
            }

            $byId = [];
            foreach ($found as $d) {
                $byId[$d['id']] = $d;
            }
            $found = array_values($byId);
            usort($found, static fn (array $a, array $b) => strcmp((string) $b['created_at'], (string) $a['created_at']));

            $data['documents'] = $found;

            // Letters generated from the company's templates. Nothing is
            // stored for these (they render live from the employee's saved
            // data via TemplatedDocument), so they are listed from the same
            // source as the Actions menu: only types this company has an
            // ACTIVE template for, that the viewer may generate, and that
            // the employee's own records can feed. 'issued' rows belong to a
            // dated record (offer sent, joined, increment, payslip);
            // 'ondemand' rows are generated fresh each time they're opened.
            $avail     = \App\Modules\Document\Services\DocumentAvailability::shared();
            $companyId = (int) $employee['company_id'];
            $render    = static fn (string $type, int $rid, bool $inline): string => site_url('documents/render/' . $type . '/' . $rid . ($inline ? '?inline=1' : ''));
            $generated = [];
            $seen      = [];
            $add       = static function (array $row) use (&$generated, &$seen): void {
                $row['menu'] = ! isset($seen[$row['type']]);
                $seen[$row['type']] = true;
                $generated[] = $row;
            };

            if ($onboarding !== null && can('onboarding.view')) {
                $obId = (int) $onboarding['id'];
                $offerOut = ! empty($onboarding['offer_sent_at']) || in_array($onboarding['status'], array_merge(['offer_accepted'], \App\Modules\Onboarding\Controllers\OnboardingController::EMPLOYEE_ELIGIBLE_STATUSES), true);
                if ($offerOut && $avail->has('offer_letter', $companyId)) {
                    $add(['type' => 'offer_letter', 'label' => 'Offer Letter', 'icon' => 'fa-file-lines', 'kind' => 'issued',
                        'issued' => $onboarding['offer_sent_at'] ?? null, 'status' => ! empty($onboarding['offer_sent_at']) ? 'Sent' : 'Accepted',
                        'view' => site_url('hr/onboarding/' . $obId . '/offer-letter'), 'download' => site_url('hr/onboarding/' . $obId . '/offer-letter/pdf')]);
                }
                if (! empty($onboarding['joined_at']) && $avail->has('joining_letter', $companyId)) {
                    $add(['type' => 'joining_letter', 'label' => 'Appointment / Joining Letter', 'icon' => 'fa-file-signature', 'kind' => 'issued',
                        'issued' => $onboarding['joined_at'], 'status' => 'Issued',
                        'view' => site_url('hr/onboarding/' . $obId . '/joining-letter'), 'download' => site_url('hr/onboarding/' . $obId . '/joining-letter/pdf')]);
                }
                foreach ($avail->items('onboarding', $companyId, ['onboarding_record' => $obId], ['offer_letter', 'joining_letter']) as $it) {
                    $add(['type' => $it['type'], 'label' => $it['label'], 'icon' => 'fa-file-pdf', 'kind' => 'ondemand', 'issued' => null, 'status' => 'Ready to generate',
                        'view' => $render($it['type'], $it['recordId'], true), 'download' => $render($it['type'], $it['recordId'], false)]);
                }
            }

            if ($avail->has('increment_letter', $companyId) && DocumentTypeRegistry::canGenerate('increment_letter')) {
                foreach ($data['increments'] ?? [] as $inc) {
                    $add(['type' => 'increment_letter', 'label' => 'Increment Letter', 'icon' => 'fa-file-invoice-dollar', 'kind' => 'issued',
                        'issued' => $inc['effective_date'], 'status' => 'Issued',
                        'view' => site_url('hr/employees/' . $profileId . '/increment-letters/' . $inc['id']), 'download' => $render('increment_letter', (int) $inc['id'], false)]);
                }
            }

            if (! empty($data['payslips']) && $avail->has('payslip', $companyId) && DocumentTypeRegistry::canGenerate('payslip')) {
                $ps = $data['payslips'][0];
                $add(['type' => 'payslip', 'label' => 'Payslip — ' . date('M Y', mktime(0, 0, 0, (int) $ps['month'], 1, (int) $ps['year'])), 'icon' => 'fa-money-check-dollar', 'kind' => 'issued',
                    'issued' => sprintf('%04d-%02d-01', $ps['year'], $ps['month']), 'status' => ucwords(str_replace('_', ' ', (string) $ps['run_status'])),
                    'view' => site_url('hr/payroll/payslip/' . $ps['id']), 'download' => site_url('hr/payroll/payslip/' . $ps['id'] . '/pdf')]);
            }

            $contexts = ['employee' => ['employee_profile' => $profileId, 'salary_structure' => (int) ($data['salary']['id'] ?? 0)]];
            if ($offboarding !== null) {
                $contexts['offboarding'] = ['offboarding_record' => (int) $offboarding['id'], 'employee_profile' => $profileId];
            }
            foreach ($contexts as $workflow => $ctx) {
                foreach ($avail->items($workflow, $companyId, $ctx, ['increment_letter', 'payslip']) as $it) {
                    if (isset($seen[$it['type']])) {
                        continue;
                    }
                    $add(['type' => $it['type'], 'label' => $it['label'], 'icon' => 'fa-file-pdf', 'kind' => 'ondemand', 'issued' => null, 'status' => 'Ready to generate',
                        'view' => $render($it['type'], $it['recordId'], true), 'download' => $render($it['type'], $it['recordId'], false)]);
                }
            }

            $data['generatedDocs'] = $generated;

            $soon = date('Y-m-d', strtotime('+30 days'));
            foreach ($found as $d) {
                if (empty($d['expiry_date']) || $d['expiry_date'] > $soon) {
                    continue;
                }
                $expired   = $d['expiry_date'] < $today;
                $actions[] = [$expired ? 'danger' : 'warning', 'fa-file-circle-exclamation', '"' . $d['title'] . '" ' . ($expired ? 'expired on ' : 'expires on ') . date('d/m/Y', strtotime($d['expiry_date']))];
            }
        }

        // Each missing detail says WHERE it is filled in (and links straight
        // to that field when the viewer may edit it), because the person
        // looking at this card often doesn't know which screen holds it.
        $editUrl   = can('employee.edit') ? site_url('hr/employees/' . $profileId . '/edit') : null;
        $phoneUrl  = can('user.edit') ? site_url('users/' . $userId . '/edit?focus=phone') : null;
        $fieldHelp = [];
        if (empty($employee['date_of_birth'])) {
            $fieldHelp[] = ['Date of birth', 'Edit Profile → Personal & Emergency Contact → Date of Birth', $editUrl ? $editUrl . '?focus=date_of_birth' : null];
        }
        if (empty($employee['user_phone'])) {
            $fieldHelp[] = ['Phone', 'Saved on the login account: Users → Edit User → Phone (the employee can also add it from My Profile)', $phoneUrl];
        }
        if (empty($employee['emergency_contact_name']) || empty($employee['emergency_contact_phone'])) {
            $fieldHelp[] = ['Emergency contact', 'Edit Profile → Personal & Emergency Contact → Emergency Contact Name / Phone', $editUrl ? $editUrl . '?focus=emergency_contact_name' : null];
        }
        if (empty($employee['reporting_manager_id'])) {
            $fieldHelp[] = ['Reporting manager', 'Edit Profile → Employment Details → Reporting Manager', $editUrl ? $editUrl . '?focus=reporting_manager_id' : null];
        }
        if ($fieldHelp !== []) {
            $actions[] = ['info', 'fa-address-card', 'Profile incomplete — missing ' . strtolower(implode(', ', array_column($fieldHelp, 0))), $fieldHelp];
        }

        // Recent activity: changes to this profile, their salary structure,
        // their leave requests and their onboarding / exit records.
        $db       = db_connect();
        $leaveIds = array_column($db->table('leave_requests')->select('id')->where('user_id', $userId)->get()->getResultArray(), 'id');
        $activity = $db->table('activity_logs al')
            ->select('al.module, al.action, al.description, al.created_at, actor.name as actor_name')
            ->join('users actor', 'actor.id = al.user_id', 'left')
            ->groupStart()
                ->groupStart()->where('al.module', 'employee')->where('al.record_id', $profileId)->groupEnd()
                ->orGroupStart()->where('al.module', 'payroll')->where('al.record_id', $userId)->like('al.description', 'salary structure', 'both')->groupEnd();
        if ($leaveIds !== []) {
            $activity->orGroupStart()->where('al.module', 'leave')->whereIn('al.record_id', $leaveIds)->groupEnd();
        }
        if ($onboarding !== null) {
            $activity->orGroupStart()->where('al.module', 'onboarding')->where('al.record_id', $onboarding['id'])->groupEnd();
        }
        if ($offboarding !== null) {
            $activity->orGroupStart()->where('al.module', 'offboarding')->where('al.record_id', $offboarding['id'])->groupEnd();
        }
        $data['activity'] = $activity->groupEnd()->orderBy('al.id', 'DESC')->limit(40)->get()->getResultArray();

        $rank = ['danger' => 0, 'warning' => 1, 'info' => 2];
        usort($actions, static fn (array $a, array $b) => $rank[$a[0]] <=> $rank[$b[0]]);

        $data['metrics'] = $metrics;
        $data['actions'] = $actions;

        return $data;
    }

    public function edit(int $id)
    {
        $employee = $this->profileModel->withRelations($id);

        if ($employee === null || $this->outOfScope(self::COMPANY_SCOPED_ROLES, $employee['company_id'] ?? null)) {
            return redirect()->to('/hr/employees')->with('error', 'Employee not found.');
        }

        return view('App\Modules\HR\employees/form', $this->formData($employee));
    }

    public function update(int $id)
    {
        $employee = $this->profileModel->find($id);

        if ($employee === null || $this->outOfScope(self::COMPANY_SCOPED_ROLES, $employee['company_id'] ?? null)) {
            return redirect()->to('/hr/employees')->with('error', 'Employee not found.');
        }

        $rules = $this->profileModel->getValidationRules();
        $rules['user_id']       = "required|integer|is_unique[employee_profiles.user_id,id,{$id}]";
        $rules['employee_code'] = "required|regex_match[/^[A-Za-z0-9-]+\$/]|max_length[20]|is_unique[employee_profiles.employee_code,id,{$id}]";

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $dob = $this->request->getPost('date_of_birth');
        if ($dob && $dob > date('Y-m-d')) {
            return redirect()->back()->withInput()->with('errors', ['Date of birth cannot be in the future.']);
        }

        if ($this->outOfScope(self::COMPANY_SCOPED_ROLES, (int) $this->request->getPost('company_id'))) {
            return redirect()->back()->withInput()->with('error', 'You can only assign employees to your own company.');
        }

        if ($this->managerMismatch((int) $this->request->getPost('company_id'))) {
            return redirect()->back()->withInput()->with('error', 'Choose a reporting manager from the same company.');
        }

        $photoError = $this->validatePhoto();
        if ($photoError !== null) {
            return redirect()->back()->withInput()->with('errors', [$photoError]);
        }

        // skipValidation: already validated above with the real id
        // interpolated directly into the unique rules (line 354-355) —
        // EmployeeProfileModel's own $validationRules still carries the
        // unresolved is_unique[...,id,{id}] form (CI4's {id} placeholder
        // only fills in from a same-named POST field, which a route
        // param never is), so without this, Model::update()'s automatic
        // re-validation silently fails and returns false — this exact
        // bug was confirmed live: editing an employee without changing
        // employee_code showed a success message but saved nothing.
        $this->profileModel->skipValidation(true)->update($id, $this->payload(false));
        $this->savePhoto((int) $employee['user_id']);
        if ($this->request->getPost('remove_photo') && ! $this->hasPhotoUpload()) {
            $this->removePhoto((int) $employee['user_id']);
        }
        $this->logActivity('employee', 'update', $id, 'Updated employee profile #' . $id);

        return redirect()->to('/hr/employees/' . $id)->with('success', 'Employee profile updated.');
    }

    public function delete(int $id)
    {
        $employee = $this->profileModel->find($id);

        if ($employee === null || $this->outOfScope(self::COMPANY_SCOPED_ROLES, $employee['company_id'] ?? null)) {
            return redirect()->to('/hr/employees')->with('error', 'Employee not found.');
        }

        $this->profileModel->delete($id);
        $this->logActivity('employee', 'delete', $id, 'Deleted employee profile #' . $id);

        return redirect()->to('/hr/employees')->with('success', 'Employee profile deleted.');
    }

    private function formData(?array $employee): array
    {
        $existingUserIds = array_column($this->profileModel->findAll(), 'user_id');
        $availableUsers  = array_filter(
            $this->userModel->listForOptions(),
            static fn (array $u) => ! in_array((int) $u['id'], $existingUserIds, true) || ($employee && (int) $u['id'] === (int) $employee['user_id'])
        );

        $companies = $this->scopedCompanyOptions($this->companyModel->optionsList());

        // One preview code per company (each company now has its own
        // employee_code prefix/sequence — see EmployeeProfileModel::
        // nextEmployeeCode()) — the create form's JS swaps the visible
        // suggestion when the admin changes the Company dropdown,
        // exactly like the Country -> State cascade elsewhere in the app.
        $nextCodeByCompany = [];
        foreach ($companies as $c) {
            $nextCodeByCompany[$c['id']] = $this->profileModel->nextEmployeeCode((int) $c['id']);
        }

        // Hired-but-not-yet-converted onboarding candidates, company-scoped —
        // surfaced on the create form as links to the primary conversion
        // path (Onboarding → Create Employee Record), since they have no
        // employee profile yet and so aren't useful as plain "User" rows.
        $onboardingCandidates = [];
        if ($employee === null && can('onboarding.view')) {
            $builder = (new \App\Modules\Onboarding\Models\OnboardingRecordModel())->filtered()
                ->whereIn('onboarding_records.status', \App\Modules\Onboarding\Controllers\OnboardingController::EMPLOYEE_ELIGIBLE_STATUSES)
                ->where('onboarding_records.employee_profile_id IS NULL');
            $scope = $this->companyScopeFor(self::COMPANY_SCOPED_ROLES);
            if ($scope !== null) {
                $builder->where('onboarding_records.company_id', $scope);
            }
            $onboardingCandidates = $builder->findAll();
        }

        return [
            'onboardingCandidates' => $onboardingCandidates,
            'title'     => $employee ? 'Edit Employee Profile' : 'Add Employee Profile',
            'navActive' => 'hr-employees',
            'employee'  => $employee,
            'users'     => $availableUsers,
            'allUsers'  => $this->userModel->listForOptions(),
            'companies' => $companies,
            'departments' => $this->departmentModel->optionsList(),
            'employmentTypes' => EmployeeProfileModel::EMPLOYMENT_TYPES,
            'statuses'  => EmployeeProfileModel::STATUSES,
            'nextCode'  => $employee['employee_code'] ?? ($nextCodeByCompany[$companies[0]['id'] ?? 0] ?? ''),
            'nextCodeByCompany' => $nextCodeByCompany,
        ];
    }

    /** Optional photo upload: returns an error message, or null when absent/valid. */
    private function validatePhoto(): ?string
    {
        $file = $this->request->getFile('photo');
        if ($file === null || $file->getError() === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if (! $file->isValid()) {
            return 'The photo could not be uploaded.';
        }
        if (! in_array($file->getMimeType(), ['image/jpeg', 'image/png', 'image/webp'], true)) {
            return 'Photo must be a JPG, PNG or WEBP image.';
        }
        if ($file->getSizeByUnit('kb') > 2048) {
            return 'Photo must be 2 MB or smaller.';
        }

        return null;
    }

    private function hasPhotoUpload(): bool
    {
        $file = $this->request->getFile('photo');

        return $file !== null && $file->getError() !== UPLOAD_ERR_NO_FILE;
    }

    /** Un-upload: clears the user's avatar and deletes the stored file. */
    private function removePhoto(int $userId): void
    {
        $old = $this->userModel->find($userId)['avatar_path'] ?? null;
        $this->userModel->skipValidation(true)->update($userId, ['avatar_path' => null]);

        if ($old && is_file(FCPATH . $old)) {
            @unlink(FCPATH . $old);
        }
    }

    /**
     * The photo is the user's avatar (users.avatar_path), the same one the
     * header, "My Profile" and this employee's profile page already show.
     */
    private function savePhoto(int $userId): void
    {
        $file = $this->request->getFile('photo');
        if ($userId <= 0 || $file === null || ! $file->isValid() || $file->hasMoved()) {
            return;
        }

        $targetDir = FCPATH . 'uploads/avatars/' . $userId;
        if (! is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        $old = $this->userModel->find($userId)['avatar_path'] ?? null;
        $name = $file->getRandomName();
        $file->move($targetDir, $name);
        $this->userModel->skipValidation(true)->update($userId, ['avatar_path' => 'uploads/avatars/' . $userId . '/' . $name]);

        if ($old && is_file(FCPATH . $old)) {
            @unlink(FCPATH . $old);
        }
    }

    /**
     * A reporting manager must be an employee of the same company, as Onboarding already requires for a
     * candidate's manager. Blank is allowed.
     */
    private function managerMismatch(int $companyId): bool
    {
        $managerId = (int) $this->request->getPost('reporting_manager_id');

        return $managerId > 0 && db_connect()->table('employee_profiles')->where('user_id', $managerId)->where('company_id', $companyId)->countAllResults() === 0;
    }

    private function payload(bool $isNew): array
    {
        return [
            'user_id'                 => (int) $this->request->getPost('user_id'),
            'company_id'              => (int) $this->request->getPost('company_id'),
            'department_id'           => $this->request->getPost('department_id') ?: null,
            'employee_code'           => $this->request->getPost('employee_code'),
            'designation'             => $this->request->getPost('designation'),
            'reporting_manager_id'    => $this->request->getPost('reporting_manager_id') ?: null,
            'employment_type'         => $this->request->getPost('employment_type') ?: 'full_time',
            'status'                  => $this->request->getPost('status') ?: 'active',
            'date_of_joining'         => $this->request->getPost('date_of_joining') ?: null,
            'date_of_birth'           => $this->request->getPost('date_of_birth') ?: null,
            'address'                 => $this->request->getPost('address'),
            'emergency_contact_name'  => $this->request->getPost('emergency_contact_name'),
            'emergency_contact_phone' => $this->request->getPost('emergency_contact_phone'),
        ];
    }
}
