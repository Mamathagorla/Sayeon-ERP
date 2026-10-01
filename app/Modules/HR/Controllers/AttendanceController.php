<?php

namespace App\Modules\HR\Controllers;

use App\Controllers\BaseController;
use App\Modules\Auth\Models\UserModel;
use App\Modules\Dashboard\Controllers\DashboardController;
use App\Modules\Department\Models\DepartmentModel;
use App\Modules\HR\Models\AttendanceModel;
use App\Modules\HR\Models\EmployeeProfileModel;

class AttendanceController extends BaseController
{
    /**
     * Roles that see the "Last Login" column — account-security/audit
     * info, not something Employee needs about their own row (they
     * already see it on their own profile) or that other roles need.
     */
    private const LOGIN_VISIBLE_ROLES = ['super_admin', 'admin', 'manager', 'hr'];

    /**
     * The app has no configured shift/early-leave policy anywhere else
     * (see DashboardController::LATE_AFTER's own comment on the same
     * point) — this is a new, clearly-labelled default introduced only
     * for the Attendance page's "Early Departures" tab, not an enforced
     * rule read anywhere else. Adjust here if the real policy differs.
     */
    private const EARLY_BEFORE = '17:00:00';

    protected AttendanceModel $attendanceModel;
    protected UserModel $userModel;
    protected EmployeeProfileModel $employeeModel;
    protected DepartmentModel $departmentModel;

    public function __construct()
    {
        $this->attendanceModel = new AttendanceModel();
        $this->userModel       = new UserModel();
        $this->employeeModel   = new EmployeeProfileModel();
        $this->departmentModel = new DepartmentModel();
    }

    /**
     * Super Admin doesn't check in/out — explicit block on the
     * self-service actions, not just an absent permission, since Super
     * Admin bypasses the permission system entirely (PermissionFilter/
     * can() both short-circuit true for that role). They still get the
     * org-wide view() below — "view all other roles' attendance," just
     * no personal record of their own to check in/out of.
     */
    private function blockSuperAdminSelfService(): ?\CodeIgniter\HTTP\RedirectResponse
    {
        if (session('roleSlug') === 'super_admin') {
            return redirect()->to('/hr/attendance')->with('error', 'Super Admin views attendance, not check-in/out.');
        }

        return null;
    }

    /**
     * Team/org view. Employees are always scoped to their own record —
     * same personal-scope treatment as Tasks and Meetings. Manager/HR/
     * Company Admin are additionally company-scoped (see
     * companyScopeFor()); only Super Admin stays org-wide across every
     * company.
     */
    public function index()
    {
        $filters = array_filter($this->request->getGet(['user_id', 'date_from', 'date_to', 'status', 'department_id', 'q']) ?? []);

        $isPersonalScope = session('roleSlug') === 'employee';
        if ($isPersonalScope) {
            $filters['user_id'] = session('userId');
        }

        $companyScope = $this->companyScopeFor(self::COMPANY_SCOPED_ROLES);
        if ($companyScope !== null) {
            $filters['company_id'] = $companyScope;
        }

        $tab = $this->request->getGet('tab') ?: 'attendance';
        if (! in_array($tab, ['attendance', 'summary', 'late', 'early'], true)) {
            $tab = 'attendance';
        }

        $records = $this->attendanceModel->filtered($filters)->findAll(200);

        $today = date('Y-m-d');
        $todayFilters = array_filter(['company_id' => $filters['company_id'] ?? null], static fn ($v) => $v !== null);
        $todayCounts  = array_fill_keys(AttendanceModel::STATUSES, 0);
        foreach ($this->attendanceModel->filtered(array_merge($todayFilters, ['date_from' => $today, 'date_to' => $today]))->findAll() as $r) {
            $todayCounts[$r['status']]++;
        }

        $employeeScope = array_filter(['company_id' => $filters['company_id'] ?? null, 'status' => 'active'], static fn ($v) => $v !== null);

        // Summary tab: per-employee rollup over the currently filtered
        // date range/status/department — same records already fetched
        // for the main tab, just regrouped, so it always agrees with it.
        $summaryByUser = [];
        foreach ($records as $r) {
            $uid = $r['user_id'];
            if (! isset($summaryByUser[$uid])) {
                $summaryByUser[$uid] = ['user_name' => $r['user_name'], 'department_name' => $r['department_name'], 'present' => 0, 'absent' => 0, 'late' => 0, 'onLeave' => 0, 'total' => 0];
            }
            $summaryByUser[$uid]['total']++;
            if (in_array($r['status'], ['present', 'half_day'], true)) {
                $summaryByUser[$uid]['present']++;
                if ($r['check_in'] && date('H:i:s', strtotime($r['check_in'])) > DashboardController::LATE_AFTER) {
                    $summaryByUser[$uid]['late']++;
                }
            } elseif ($r['status'] === 'absent') {
                $summaryByUser[$uid]['absent']++;
            } elseif ($r['status'] === 'on_leave') {
                $summaryByUser[$uid]['onLeave']++;
            }
        }

        $lateRecords = array_values(array_filter($records, static fn (array $r) => $r['check_in'] && date('H:i:s', strtotime($r['check_in'])) > DashboardController::LATE_AFTER));
        $earlyRecords = array_values(array_filter($records, static fn (array $r) => $r['check_out'] && date('H:i:s', strtotime($r['check_out'])) < self::EARLY_BEFORE));

        return view('App\Modules\HR\attendance/index', [
            'title'           => $isPersonalScope ? 'My Attendance' : 'Attendance',
            'navActive'       => 'hr-attendance',
            'isPersonalScope' => $isPersonalScope,
            'canSeeLogins'    => in_array(session('roleSlug'), self::LOGIN_VISIBLE_ROLES, true),
            'canManage'       => can('attendance.edit'),
            'tab'             => $tab,
            'records'         => $records,
            'summaryByUser'   => $summaryByUser,
            'lateRecords'     => $lateRecords,
            'earlyRecords'    => $earlyRecords,
            'kpi'             => [
                'totalEmployees' => $isPersonalScope ? null : $this->employeeModel->filtered($employeeScope)->countAllResults(),
                'presentToday'   => $todayCounts['present'] + $todayCounts['half_day'],
                'absentToday'    => $todayCounts['absent'],
                'onLeaveToday'   => $todayCounts['on_leave'],
            ],
            'users'           => $this->scopedUserOptions($this->userModel->listForOptions()),
            'departments'     => $this->departmentModel->optionsList(),
            'statuses'        => AttendanceModel::STATUSES,
            'filters'         => $filters,
            'today'           => $today,
            'lateAfter'       => DashboardController::LATE_AFTER,
            'earlyBefore'     => self::EARLY_BEFORE,
        ]);
    }

    /**
     * HR/Super Admin marking (or correcting) a single employee's
     * attendance for a given day — the admin-side counterpart to the
     * self-service checkIn()/checkOut() above. Upserts the same way
     * those do: one row per (user_id, date).
     */
    public function markForm()
    {
        return view('App\Modules\HR\attendance/mark', [
            'title'     => 'Mark Attendance',
            'navActive' => 'hr-attendance',
            'employees' => $this->employeeModel->filtered(array_filter(['company_id' => $this->companyScopeFor(self::COMPANY_SCOPED_ROLES)], static fn ($v) => $v !== null))->findAll(),
            'statuses'  => AttendanceModel::STATUSES,
            'today'     => date('Y-m-d'),
        ]);
    }

    public function mark()
    {
        $userId = (int) $this->request->getPost('user_id');
        $date   = $this->request->getPost('date');
        $status = $this->request->getPost('status');

        $employee = $this->employeeModel->byUserId($userId);

        if ($employee === null || $this->outOfScope(self::COMPANY_SCOPED_ROLES, $employee['company_id'] ?? null)) {
            return redirect()->to('/hr/attendance/mark')->with('error', 'Select a valid employee from your own company.');
        }

        if (! in_array($status, AttendanceModel::STATUSES, true) || empty($date)) {
            return redirect()->back()->withInput()->with('error', 'Select a valid date and status.');
        }

        $existing = $this->attendanceModel->where('user_id', $userId)->where('date', $date)->first();

        // A blank Check In/Out on the form means "leave it as-is", not
        // "clear it" — only overwrite when the admin actually typed a
        // time, otherwise correcting just the day's status would silently
        // wipe an employee's real punch times already on record.
        $checkIn  = $this->request->getPost('check_in') ? $date . ' ' . $this->request->getPost('check_in') . ':00' : ($existing['check_in'] ?? null);
        $checkOut = $this->request->getPost('check_out') ? $date . ' ' . $this->request->getPost('check_out') . ':00' : ($existing['check_out'] ?? null);

        $data = ['user_id' => $userId, 'date' => $date, 'status' => $status, 'check_in' => $checkIn, 'check_out' => $checkOut, 'notes' => $this->request->getPost('notes') ?: null];

        if ($existing !== null) {
            $this->attendanceModel->update($existing['id'], $data);
        } else {
            $this->attendanceModel->insert($data);
        }

        $this->logActivity('attendance', 'update', $userId, 'Marked attendance for ' . $employee['user_name'] . ' on ' . $date . ' as ' . $status);

        return redirect()->to('/hr/attendance')->with('success', 'Attendance marked for ' . $employee['user_name'] . '.');
    }

    /**
     * CSV bulk import — one row per employee/day: employee_code, date
     * (YYYY-MM-DD), status, check_in (HH:MM, optional), check_out (HH:MM,
     * optional). Same upsert-by-(user_id, date) as mark()/checkIn(), and
     * the same company-scope guard, applied per row (a row for an
     * employee outside the uploader's own company is skipped, not
     * silently trusted). Bad rows are skipped and counted, never fatal —
     * one malformed line shouldn't sink the whole file.
     */
    public function bulkUploadForm()
    {
        return view('App\Modules\HR\attendance/bulk_upload', [
            'title'     => 'Bulk Upload Attendance',
            'navActive' => 'hr-attendance',
        ]);
    }

    public function bulkUpload()
    {
        $file = $this->request->getFile('csv');

        if ($file === null || ! $file->isValid() || $file->getClientExtension() !== 'csv') {
            return redirect()->to('/hr/attendance/bulk-upload')->with('error', 'Upload a valid .csv file.');
        }

        $handle = fopen($file->getTempName(), 'r');
        if ($handle === false) {
            return redirect()->to('/hr/attendance/bulk-upload')->with('error', 'Could not read the uploaded file.');
        }

        $companyScope = $this->companyScopeFor(self::COMPANY_SCOPED_ROLES);
        $header = fgetcsv($handle);
        $imported = 0;
        $skipped  = 0;

        while (($row = fgetcsv($handle)) !== false) {
            [$code, $date, $status, $checkIn, $checkOut] = array_pad($row, 5, null);
            $employee = $code ? $this->employeeModel->byEmployeeCode(trim($code)) : null;

            if ($employee === null
                || ($companyScope !== null && (int) $employee['company_id'] !== $companyScope)
                || empty($date)
                || ! in_array($status, AttendanceModel::STATUSES, true)
            ) {
                $skipped++;
                continue;
            }

            $existing = $this->attendanceModel->where('user_id', $employee['user_id'])->where('date', trim($date))->first();

            // Same "blank means leave it as-is" rule as mark() — a row
            // that only sets status shouldn't erase a punch time already
            // on record for that day.
            $data = [
                'user_id'   => $employee['user_id'],
                'date'      => trim($date),
                'status'    => trim($status),
                'check_in'  => $checkIn ? trim($date) . ' ' . trim($checkIn) . ':00' : ($existing['check_in'] ?? null),
                'check_out' => $checkOut ? trim($date) . ' ' . trim($checkOut) . ':00' : ($existing['check_out'] ?? null),
            ];

            if ($existing !== null) {
                $this->attendanceModel->update($existing['id'], $data);
            } else {
                $this->attendanceModel->insert($data);
            }
            $imported++;
        }
        fclose($handle);

        $this->logActivity('attendance', 'create', null, 'Bulk-uploaded attendance: ' . $imported . ' row(s) imported, ' . $skipped . ' skipped');

        return redirect()->to('/hr/attendance')->with('success', $imported . ' row(s) imported' . ($skipped > 0 ? ', ' . $skipped . ' skipped (unknown employee code, wrong company, or invalid data).' : '.'));
    }

    /**
     * A single employee's attendance history — reached by clicking their
     * name from the org-wide index() list. Same company-scope guard as
     * the Employee Profile page; defaults to the current calendar month
     * when no period filter is given.
     */
    public function employee(int $userId)
    {
        $employee = $this->employeeModel->byUserId($userId);

        if ($employee === null || $this->outOfScope(self::COMPANY_SCOPED_ROLES, $employee['company_id'] ?? null)) {
            return redirect()->to('/hr/attendance')->with('error', 'Employee not found.');
        }

        $filters = array_filter($this->request->getGet(['date_from', 'date_to', 'status']) ?? []);
        if (empty($filters['date_from']) && empty($filters['date_to'])) {
            $filters['date_from'] = date('Y-m-01');
        }

        $records = $this->attendanceModel->filtered(array_merge($filters, ['user_id' => $userId]))->findAll(200);

        // Same on-time/late split as the Employee Profile's attendance
        // donut — "late" isn't a stored status, it's a present row whose
        // check-in is after the app-wide cutoff.
        $onTime = $late = $absent = $onLeave = $holiday = $weekOff = 0;
        foreach ($records as $r) {
            if (in_array($r['status'], ['present', 'half_day'], true)) {
                if ($r['check_in'] && date('H:i:s', strtotime($r['check_in'])) > DashboardController::LATE_AFTER) {
                    $late++;
                } else {
                    $onTime++;
                }
            } elseif ($r['status'] === 'absent') {
                $absent++;
            } elseif ($r['status'] === 'on_leave') {
                $onLeave++;
            } elseif ($r['status'] === 'holiday') {
                $holiday++;
            } elseif ($r['status'] === 'week_off') {
                $weekOff++;
            }
        }
        $attended = $onTime + $late;
        $pct      = ($attended + $absent) > 0 ? (int) round($attended / ($attended + $absent) * 100) : null;

        return view('App\Modules\HR\attendance/employee', [
            'title'        => 'Attendance History',
            'navActive'    => 'hr-attendance',
            'employee'     => $employee,
            'records'      => $records,
            'summary'      => [
                'onTime' => $onTime, 'late' => $late, 'absent' => $absent, 'onLeave' => $onLeave,
                'workingDays' => count($records) - $holiday - $weekOff, 'pct' => $pct, 'total' => count($records),
            ],
            'statuses'     => AttendanceModel::STATUSES,
            'filters'      => $filters,
        ]);
    }

    /**
     * Personal self-service page: today's status + check-in/out
     * buttons + this month's summary + recent history.
     */
    public function mine()
    {
        if ($blocked = $this->blockSuperAdminSelfService()) {
            return $blocked;
        }

        $userId = (int) $this->currentUserId();
        $today  = date('Y-m-d');

        return view('App\Modules\HR\attendance/mine', [
            'title'     => 'My Attendance',
            'navActive' => 'hr-attendance',
            'today'     => $this->attendanceModel->todayFor($userId),
            'summary'   => $this->attendanceModel->monthSummary($userId, (int) date('n'), (int) date('Y')),
            'recent'    => $this->attendanceModel->where('user_id', $userId)->orderBy('date', 'DESC')->findAll(14),
            'monthLabel' => date('F Y'),
        ]);
    }

    public function checkIn()
    {
        if ($blocked = $this->blockSuperAdminSelfService()) {
            return $blocked;
        }

        $userId = (int) $this->currentUserId();
        $today  = $this->attendanceModel->todayFor($userId);

        if ($today !== null && $today['check_in'] !== null) {
            return redirect()->to('/hr/attendance/mine')->with('error', 'You already checked in today.');
        }

        $data = ['user_id' => $userId, 'date' => date('Y-m-d'), 'check_in' => date('Y-m-d H:i:s'), 'status' => 'present'];

        if ($today !== null) {
            $this->attendanceModel->update($today['id'], $data);
        } else {
            $this->attendanceModel->insert($data);
        }

        return redirect()->to('/hr/attendance/mine')->with('success', 'Checked in at ' . date('g:i A') . '.');
    }

    public function checkOut()
    {
        if ($blocked = $this->blockSuperAdminSelfService()) {
            return $blocked;
        }

        $userId = (int) $this->currentUserId();
        $today  = $this->attendanceModel->todayFor($userId);

        if ($today === null || $today['check_in'] === null) {
            return redirect()->to('/hr/attendance/mine')->with('error', 'Check in before checking out.');
        }

        if ($today['check_out'] !== null) {
            return redirect()->to('/hr/attendance/mine')->with('error', 'You already checked out today.');
        }

        $this->attendanceModel->update($today['id'], ['check_out' => date('Y-m-d H:i:s')]);

        return redirect()->to('/hr/attendance/mine')->with('success', 'Checked out at ' . date('g:i A') . '.');
    }
}
