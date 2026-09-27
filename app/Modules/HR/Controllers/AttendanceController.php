<?php

namespace App\Modules\HR\Controllers;

use App\Controllers\BaseController;
use App\Modules\Auth\Models\UserModel;
use App\Modules\Dashboard\Controllers\DashboardController;
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

    protected AttendanceModel $attendanceModel;
    protected UserModel $userModel;
    protected EmployeeProfileModel $employeeModel;

    public function __construct()
    {
        $this->attendanceModel = new AttendanceModel();
        $this->userModel       = new UserModel();
        $this->employeeModel   = new EmployeeProfileModel();
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
        $filters = array_filter($this->request->getGet(['user_id', 'date_from', 'date_to', 'status']) ?? []);

        $isPersonalScope = session('roleSlug') === 'employee';
        if ($isPersonalScope) {
            $filters['user_id'] = session('userId');
        }

        $companyScope = $this->companyScopeFor(self::COMPANY_SCOPED_ROLES);
        if ($companyScope !== null) {
            $filters['company_id'] = $companyScope;
        }

        return view('App\Modules\HR\attendance/index', [
            'title'           => $isPersonalScope ? 'My Attendance' : 'Attendance',
            'navActive'       => 'hr-attendance',
            'isPersonalScope' => $isPersonalScope,
            'canSeeLogins'    => in_array(session('roleSlug'), self::LOGIN_VISIBLE_ROLES, true),
            'records'         => $this->attendanceModel->filtered($filters)->findAll(200),
            'users'           => $this->scopedUserOptions($this->userModel->listForOptions()),
            'statuses'        => AttendanceModel::STATUSES,
            'filters'         => $filters,
        ]);
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
