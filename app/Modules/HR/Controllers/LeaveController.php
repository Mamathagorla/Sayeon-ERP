<?php

namespace App\Modules\HR\Controllers;

use App\Controllers\BaseController;
use App\Modules\Auth\Models\UserModel;
use App\Modules\HR\Models\EmployeeProfileModel;
use App\Modules\HR\Models\LeaveRequestModel;
use App\Modules\HR\Models\LeaveTypeModel;
use App\Modules\Notification\Services\NotificationService;

class LeaveController extends BaseController
{
    /**
     * Approval hierarchy: an approver may only act on requests from a
     * strictly higher (numerically larger) rank than their own.
     * super_admin(0) > admin(1) > manager/hr(2, peers) >
     * accountant/compliance_officer/employee(3). Viewer is intentionally
     * excluded — that role is read-only by design and doesn't apply
     * for leave. Admin/Manager/HR are additionally company-scoped (see
     * companyScopeFor()) — a Company Admin only reviews/approves their
     * own company's Managers, HR and staff, never another company's.
     * Only Super Admin stays org-wide.
     */
    public const ROLE_RANK = [
        'super_admin'         => 0,
        'admin'               => 1,
        'manager'             => 2,
        'hr'                  => 2,
        'accountant'          => 3,
        'compliance_officer'  => 3,
        'employee'            => 3,
    ];

    protected LeaveRequestModel $leaveModel;
    protected LeaveTypeModel $leaveTypeModel;
    protected EmployeeProfileModel $employeeModel;
    protected UserModel $userModel;
    protected NotificationService $notificationService;

    public function __construct()
    {
        $this->leaveModel      = new LeaveRequestModel();
        $this->leaveTypeModel  = new LeaveTypeModel();
        $this->employeeModel   = new EmployeeProfileModel();
        $this->userModel       = new UserModel();
        $this->notificationService = new NotificationService();
    }

    /**
     * Always shows the current user's own requests ("My Leave
     * Requests"). Approvers (Manager, HR, Admin, Super Admin)
     * additionally get "Leave Requests to Review": everyone within
     * their rank + company authority, excluding themselves.
     */
    public function index()
    {
        $filters = array_filter($this->request->getGet(['status', 'leave_type_id']) ?? []);

        // Super Admin doesn't apply for leave — nothing to show as "own" requests.
        $isSuperAdmin = session('roleSlug') === 'super_admin';

        $canApprove   = can('leave.approve');
        $myRequests   = $isSuperAdmin ? [] : $this->leaveModel->filtered(array_merge($filters, ['user_id' => session('userId')]))->findAll();
        $teamRequests = [];

        if ($canApprove) {
            $teamFilters = array_merge($filters, ['requester_role_slugs' => $this->approvableRoleSlugs()]);

            $companyScope = $this->companyScopeFor(['admin', 'manager', 'hr']);
            if ($companyScope !== null) {
                $teamFilters['company_id'] = $companyScope;
            }

            $teamRequests = $this->leaveModel->filtered($teamFilters)->findAll();
        }

        return view('App\Modules\HR\leave/index', [
            'title'        => 'Leave Requests',
            'navActive'    => 'hr-leave',
            'canApprove'   => $canApprove,
            'myRequests'   => $myRequests,
            'teamRequests' => $teamRequests,
            'leaveTypes'   => $this->leaveTypeModel->optionsList(),
            'balances'     => $isSuperAdmin ? [] : $this->balancesFor((int) session('userId')),
            'filters'      => $filters,
        ]);
    }

    /**
     * A single employee's leave history — reached by clicking their
     * name from "Leave Requests to Review". Same company-scope guard
     * used across the Employee Profile / Attendance History pages.
     */
    public function employee(int $userId)
    {
        $employee = $this->employeeModel->byUserId($userId);

        if ($employee === null || $this->outOfScope(['admin', 'manager', 'hr'], $employee['company_id'] ?? null)) {
            return redirect()->to('/hr/leave')->with('error', 'Employee not found.');
        }

        $filters = array_filter($this->request->getGet(['year', 'leave_type_id', 'status']) ?? []);
        $filters['year'] = $filters['year'] ?? date('Y');

        $records = $this->leaveModel->filtered(array_merge($filters, ['user_id' => $userId]))->findAll(200);
        $pending = count(array_filter($records, static fn (array $r) => $r['status'] === 'pending'));
        $balances = $this->balancesFor($userId);

        return view('App\Modules\HR\leave/employee', [
            'title'      => 'Leave History',
            'navActive'  => 'hr-leave',
            'employee'   => $employee,
            'records'    => $records,
            'balances'   => $balances,
            'totals'     => [
                'quota'     => array_sum(array_column($balances, 'quota')),
                'used'      => array_sum(array_column($balances, 'used')),
                'remaining' => array_sum(array_column($balances, 'remaining')),
                'pending'   => $pending,
            ],
            'leaveTypes' => $this->leaveTypeModel->optionsList(),
            'filters'    => $filters,
        ]);
    }

    public function create()
    {
        if (session('roleSlug') === 'super_admin') {
            return redirect()->to('/hr/leave')->with('error', 'Super Admin does not apply for leave.');
        }

        return view('App\Modules\HR\leave/form', [
            'title'      => 'Apply for Leave',
            'navActive'  => 'hr-leave',
            'leaveTypes' => $this->leaveTypeModel->optionsList(),
            'balances'   => $this->balancesFor((int) $this->currentUserId()),
        ]);
    }

    public function store()
    {
        if (session('roleSlug') === 'super_admin') {
            return redirect()->to('/hr/leave')->with('error', 'Super Admin does not apply for leave.');
        }

        $startDate = $this->request->getPost('start_date');
        $endDate   = $this->request->getPost('end_date');

        if (! $this->validate($this->leaveModel->getValidationRules())) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        if ($endDate < $startDate) {
            return redirect()->back()->withInput()->with('error', 'End date cannot be before the start date.');
        }

        $userId = (int) $this->currentUserId();
        $days   = $this->leaveModel->calculateDays($startDate, $endDate);

        $id = $this->leaveModel->insert([
            'user_id'       => $userId,
            'leave_type_id' => (int) $this->request->getPost('leave_type_id'),
            'start_date'    => $startDate,
            'end_date'      => $endDate,
            'days'          => $days,
            'reason'        => $this->request->getPost('reason'),
            'status'        => 'pending',
        ]);

        $this->logActivity('leave', 'create', $id, 'Applied for leave (' . $days . ' day(s))');
        $this->notifyManager($userId, $id, $days);

        return redirect()->to('/hr/leave')->with('success', 'Leave request submitted.');
    }

    public function approve(int $id)
    {
        return $this->decide($id, 'approved');
    }

    public function reject(int $id)
    {
        return $this->decide($id, 'rejected');
    }

    public function cancel(int $id)
    {
        $request = $this->leaveModel->find($id);

        if ($request === null || (int) $request['user_id'] !== (int) $this->currentUserId() || $request['status'] !== 'pending') {
            return redirect()->to('/hr/leave')->with('error', 'This request can no longer be cancelled.');
        }

        $this->leaveModel->update($id, ['status' => 'cancelled']);
        $this->logActivity('leave', 'update', $id, 'Cancelled leave request #' . $id);

        return redirect()->to('/hr/leave')->with('success', 'Leave request cancelled.');
    }

    private function decide(int $id, string $status)
    {
        $request = $this->leaveModel->find($id);

        if ($request === null || $request['status'] !== 'pending') {
            return redirect()->to('/hr/leave')->with('error', 'This request has already been actioned.');
        }

        $myRoleSlug = session('roleSlug');
        $isSelf     = (int) $request['user_id'] === (int) $this->currentUserId();

        if ($isSelf) {
            // Top of the chain — no one else to approve it — may self-approve.
            if ($myRoleSlug !== 'super_admin') {
                return redirect()->to('/hr/leave')->with('error', 'You cannot approve or reject your own leave request.');
            }
        } else {
            $requesterRoleSlug = $this->userModel->findActiveById((int) $request['user_id'])['role_slug'] ?? null;
            $myRank        = self::ROLE_RANK[$myRoleSlug] ?? null;
            $requesterRank = self::ROLE_RANK[$requesterRoleSlug] ?? null;

            if ($myRank === null || $requesterRank === null || $myRank >= $requesterRank) {
                return redirect()->to('/hr/leave')->with('error', 'You are not authorized to approve this leave request.');
            }

            $companyScope = $this->companyScopeFor(['admin', 'manager', 'hr']);
            if ($companyScope !== null) {
                $requesterCompanyId = $this->employeeModel->byUserId((int) $request['user_id'])['company_id'] ?? null;

                if ((int) $requesterCompanyId !== $companyScope) {
                    return redirect()->to('/hr/leave')->with('error', 'You can only approve or reject leave requests from your own company.');
                }
            }
        }

        $this->leaveModel->update($id, [
            'status'      => $status,
            'approved_by' => $this->currentUserId(),
            'approved_at' => date('Y-m-d H:i:s'),
        ]);

        $this->logActivity('leave', 'update', $id, ucfirst($status) . ' leave request #' . $id);

        $this->notificationService->notify(
            (int) $request['user_id'],
            'leave_' . $status,
            'Leave request ' . $status,
            'Your leave request for ' . $request['start_date'] . ' to ' . $request['end_date'] . ' was ' . $status . '.',
            'leave',
            $id
        );

        return redirect()->to('/hr/leave')->with('success', 'Leave request ' . $status . '.');
    }

    /**
     * Role slugs strictly below the current user's rank — the set of
     * requesters they're allowed to see/approve in the list view.
     */
    private function approvableRoleSlugs(): array
    {
        $myRank = self::ROLE_RANK[session('roleSlug')] ?? null;

        if ($myRank === null) {
            return [];
        }

        return array_keys(array_filter(self::ROLE_RANK, static fn ($rank) => $rank > $myRank));
    }

    private function notifyManager(int $userId, int $leaveId, float $days): void
    {
        $profile = $this->employeeModel->byUserId($userId);

        if ($profile === null || empty($profile['reporting_manager_id'])) {
            return;
        }

        $this->notificationService->notify(
            (int) $profile['reporting_manager_id'],
            'leave_requested',
            'Leave request from ' . $profile['user_name'],
            $profile['user_name'] . ' requested ' . $days . ' day(s) of leave.',
            'leave',
            $leaveId
        );
    }

    private function balancesFor(int $userId): array
    {
        $balances = [];

        foreach ($this->leaveTypeModel->optionsList() as $type) {
            $used = $this->leaveModel->approvedDaysThisYear($userId, (int) $type['id']);

            $balances[] = [
                'name'      => $type['name'],
                'quota'     => (float) $type['annual_quota'],
                'used'      => $used,
                'remaining' => max(0, (float) $type['annual_quota'] - $used),
            ];
        }

        return $balances;
    }
}
