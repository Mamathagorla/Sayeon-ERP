<?php

namespace App\Modules\Dashboard\Controllers;

use App\Controllers\BaseController;
use App\Modules\Accounting\Models\InvoiceModel;
use App\Modules\Accounting\Models\PaymentModel;
use App\Modules\Company\Models\CompanyModel;
use App\Modules\Compliance\Models\ComplianceItemModel;
use App\Modules\Expense\Models\ExpenseModel;
use App\Modules\HR\Models\LeaveRequestModel;
use App\Modules\Meeting\Models\MeetingModel;
use App\Modules\Task\Models\TaskModel;

/**
 * Phase 1-3 dashboard covers what Task/Company/Meeting/Compliance data
 * can already answer (active/completed/overdue counts, upcoming meetings,
 * compliance alerts, company-wise progress, basic team performance).
 * Monthly Expenses stays a visible-but-empty card until Phase 5 lands.
 */
class DashboardController extends BaseController
{
    protected TaskModel $taskModel;
    protected CompanyModel $companyModel;
    protected MeetingModel $meetingModel;
    protected ComplianceItemModel $complianceModel;
    protected LeaveRequestModel $leaveModel;
    protected ExpenseModel $expenseModel;
    protected InvoiceModel $invoiceModel;
    protected PaymentModel $paymentModel;

    public function __construct()
    {
        $this->taskModel       = new TaskModel();
        $this->companyModel    = new CompanyModel();
        $this->meetingModel    = new MeetingModel();
        $this->complianceModel = new ComplianceItemModel();
        $this->leaveModel      = new LeaveRequestModel();
        $this->expenseModel    = new ExpenseModel();
        $this->invoiceModel    = new InvoiceModel();
        $this->paymentModel    = new PaymentModel();
    }

    public function index()
    {
        $roleSlug = session('roleSlug');

        // HR Manager has no task/meeting/company access, so the generic
        // dashboard is nearly empty for them — they get their own
        // workforce-focused view instead. Every other role is untouched.
        if ($roleSlug === 'hr') {
            return $this->hrDashboard();
        }

        // Employee = "works their assigned tasks" (per RoleSeeder) — gets a
        // personal view scoped to their own tasks/meetings. Every other role
        // that can see tasks/meetings at all sees the org-wide picture,
        // including Viewer ("read-only access across modules").
        $isPersonalScope = $roleSlug === 'employee';
        $userId           = $isPersonalScope ? (int) session('userId') : null;

        $canViewTasks           = can('task.view');
        $canViewMeetings        = can('meeting.view');
        $canViewCompliance      = can('compliance.view');
        // Company-wise Progress and Team Performance are org-oversight
        // widgets, not personal ones — hide them from the personal-scope view
        // even though Employee technically holds task.view/company.view.
        $canViewCompanyProgress = can('company.view') && ! $isPersonalScope;
        $canViewTeamPerformance = $canViewTasks && ! $isPersonalScope;
        $canApproveLeave        = can('leave.approve');
        $canViewExpenses        = can('expense.view') && ! $isPersonalScope;

        // Company Admin's fixed company, or Super Admin's current topbar
        // switcher selection (null = "All Companies", their default).
        $companyScope = $this->companyScopeFor(self::COMPANY_SCOPED_ROLES);

        $counts = $canViewTasks
            ? $this->taskModel->dashboardCounts($userId, $companyScope)
            : ['active' => 0, 'completed' => 0, 'overdue' => 0];

        if ($canViewCompliance) {
            $this->complianceModel->refreshOverdueStatuses();
        }

        $companyProgress = [];

        if ($canViewCompanyProgress) {
            $companies = $companyScope !== null
                ? array_filter($this->companyModel->findAll(), static fn (array $c) => (int) $c['id'] === $companyScope)
                : $this->companyModel->findAll();

            foreach ($companies as $company) {
                $total     = $this->taskModel->where('company_id', $company['id'])->countAllResults();
                $completed = $this->taskModel->where('company_id', $company['id'])->where('status', 'completed')->countAllResults();

                $companyProgress[] = [
                    'id'        => $company['id'],
                    'name'      => $company['name'],
                    'total'     => $total,
                    'completed' => $completed,
                    'percent'   => $total > 0 ? (int) round($completed / $total * 100) : 0,
                ];
            }
        }

        // Paged rather than dumping every company on the dashboard at
        // once — a handful today, but this card shouldn't get taller
        // forever as more companies are added. 4 per page keeps the
        // existing 2x2 grid both views already render; "cp" (not
        // "page") to stay clear of any future page param elsewhere on
        // this same dashboard route.
        $companyPerPage  = 5;
        $companyTotal    = count($companyProgress);
        $companyPages    = max(1, (int) ceil($companyTotal / $companyPerPage));
        $companyPage     = max(1, min($companyPages, (int) ($this->request->getGet('cp') ?: 1)));
        $companyProgress = array_slice($companyProgress, ($companyPage - 1) * $companyPerPage, $companyPerPage);

        // Daily "tasks completed" for the last 7 days — feeds the Team
        // Performance chart's day-by-day trend line.
        $dailyCompletions = [];

        if ($canViewTeamPerformance) {
            $completionQuery = $this->taskModel
                ->select('DATE(tasks.completed_at) as day, COUNT(tasks.id) as total')
                ->where('tasks.status', 'completed')
                ->where('tasks.completed_at >=', date('Y-m-d 00:00:00', strtotime('-6 days')));

            if ($companyScope !== null) {
                $completionQuery->where('tasks.company_id', $companyScope);
            }

            $byDay = array_column($completionQuery->groupBy('day')->findAll(), 'total', 'day');

            for ($i = 6; $i >= 0; $i--) {
                $day                    = date('Y-m-d', strtotime("-{$i} days"));
                $dailyCompletions[$day] = (int) ($byDay[$day] ?? 0);
            }
        }

        // Week-over-week deltas for the stat cards — genuine counts of
        // rows created in the trailing 7 days vs the 7 days before that,
        // not a fabricated trend.
        $taskTrend    = $canViewTasks ? $this->weekOverWeek($this->taskModel, 'created_at', $userId ? ['assigned_to' => $userId] : [], $companyScope) : null;
        $meetingTrend = $canViewMeetings ? $this->weekOverWeek($this->meetingModel, 'created_at', [], $companyScope) : null;

        // Sparklines: small genuine daily series backing 4 of the 5 stat
        // cards (Overdue Tasks has no clean daily signal without a status
        // snapshot table, so it gets no sparkline rather than a fake one).
        $activeTasksSeries = $canViewTasks
            ? $this->dailySeries($this->taskModel, 'created_at', $userId ? ['assigned_to' => $userId] : [], $companyScope, 'past')
            : [];
        $completedTasksSeries = array_values($dailyCompletions);
        $meetingsSeries        = $canViewMeetings
            ? $this->dailySeries($this->meetingModel, 'meeting_date', [], $companyScope, 'future')
            : [];
        $complianceSeries = $canViewCompliance
            ? $this->dailySeries($this->complianceModel, 'due_date', [], $companyScope, 'future')
            : [];

        $nextMeetings = $canViewMeetings ? $this->meetingModel->upcomingForDashboard(5, $userId, $companyScope) : [];
        foreach ($nextMeetings as &$meeting) {
            $meeting['relative'] = $this->relativeMeetingLabel($meeting['meeting_date'], $meeting['start_time'] ?? null);
        }
        unset($meeting);

        $complianceAlerts = $canViewCompliance ? $this->complianceModel->alerts(7, 5, $companyScope) : [];
        foreach ($complianceAlerts as &$alert) {
            $alert['priority'] = $this->duePriority($alert['due_date']);
        }
        unset($alert);

        $pendingLeaveRequests = $canApproveLeave
            ? $this->leaveModel->filtered(['status' => 'pending'])->findAll(5)
            : [];

        $priorities = $this->buildPriorities(
            $canViewTasks ? $this->urgentTasks($userId, $companyScope) : [],
            $complianceAlerts,
            $pendingLeaveRequests,
            $nextMeetings
        );

        // Super Admin dashboard only — a short "overdue right now" task
        // list (same overdue definition dashboardCounts() already uses)
        // and the invoice-side figures for the "Needs Attention" /
        // "Financial Overview" panels. No new permission, no new route:
        // purely additive read-only queries against models every other
        // part of the app already uses, scoped off for every other
        // role so their dashboard's behavior/queries are unchanged.
        $isSuperAdmin = $roleSlug === 'super_admin';

        $overdueTasksList    = [];
        $overdueInvoices     = [];
        $overdueInvoiceCount = 0;
        $financeRevenue      = 0.0;
        $financeOutstanding  = 0.0;

        if ($isSuperAdmin) {
            $taskFilters = $companyScope !== null ? ['company_id' => $companyScope] : [];

            $overdueTasksList = $this->taskModel->filtered($taskFilters)
                ->where('tasks.due_date <', date('Y-m-d'))
                ->whereNotIn('tasks.status', ['completed', 'cancelled'])
                ->orderBy('tasks.due_date', 'ASC')
                ->findAll(5);

            $this->invoiceModel->refreshOverdueStatuses();
            $invoiceFilters      = $companyScope !== null ? ['company_id' => $companyScope] : [];
            $overdueInvoiceCount = $this->invoiceModel->filtered($invoiceFilters)->where('invoices.status', 'overdue')->countAllResults();
            $overdueInvoices     = $this->invoiceModel->filtered($invoiceFilters)->where('invoices.status', 'overdue')->findAll(5);

            $outstandingBuilder = $this->invoiceModel->whereIn('status', InvoiceModel::OPEN_STATUSES);
            if ($companyScope !== null) {
                $outstandingBuilder->where('company_id', $companyScope);
            }
            $financeOutstanding = (float) ($outstandingBuilder->selectSum('amount')->first()['amount'] ?? 0);

            $cashTotals     = $this->paymentModel->cashInOutTotals($companyScope, date('Y-m-01'), date('Y-m-d'));
            $financeRevenue = $cashTotals['in'];
        }

        $monthlyExpenseTotal = $canViewExpenses ? $this->expenseModel->monthlyEquivalentTotal($companyScope) : 0;

        $expenseByCategory = [];
        if ($canViewExpenses) {
            $categoryQuery = $this->expenseModel
                ->select('category, SUM(amount) as total')
                ->where('status', 'active');

            if ($companyScope !== null) {
                $categoryQuery->where('company_id', $companyScope);
            }

            $expenseByCategory = $categoryQuery->groupBy('category')->orderBy('total', 'DESC')->findAll(6);
        }

        // Same data for every role — only Super Admin gets the richer
        // visual layout (super_admin_index.php); every other role keeps
        // the existing dashboard view exactly as-is.
        $view = $roleSlug === 'super_admin' ? 'App\Modules\Dashboard\super_admin_index' : 'App\Modules\Dashboard\index';

        return view($view, [
            'title'            => $isPersonalScope ? 'My Dashboard' : 'Dashboard',
            'navActive'        => 'dashboard',
            'isPersonalScope'  => $isPersonalScope,
            // companyScopeFor() only ever returns 0 for a scoped
            // (non-Super-Admin) viewer with no employee_profiles row —
            // Super Admin's scope is always null or a real company id.
            'noCompanyAssigned' => $companyScope === 0,
            'weekRangeLabel'   => $this->currentWeekLabel(),

            'canViewTasks'           => $canViewTasks,
            'canViewMeetings'        => $canViewMeetings,
            'canViewCompliance'      => $canViewCompliance,
            'canViewCompanyProgress' => $canViewCompanyProgress,
            'canViewTeamPerformance' => $canViewTeamPerformance,
            'canApproveLeave'        => $canApproveLeave,
            'pendingLeaveApprovals'  => $canApproveLeave ? count($pendingLeaveRequests) : 0,
            'canViewExpenses'        => $canViewExpenses,
            'monthlyExpenseTotal'    => $monthlyExpenseTotal,
            'expenseByCategory'      => $expenseByCategory,

            'activeTasks'      => $counts['active'],
            'completedTasks'   => $counts['completed'],
            'overdueTasks'     => $counts['overdue'],
            'taskTrend'        => $taskTrend,
            'meetingTrend'     => $meetingTrend,
            'activeTasksSeries'    => $activeTasksSeries,
            'completedTasksSeries' => $completedTasksSeries,
            'meetingsSeries'       => $meetingsSeries,
            'complianceSeries'     => $complianceSeries,
            'upcomingMeetings' => $canViewMeetings ? $this->meetingModel->upcomingCount($userId, $companyScope) : 0,
            'nextMeetings'     => $nextMeetings,
            'complianceAlertCount' => $canViewCompliance ? $this->complianceModel->alertCount(7, $companyScope) : 0,
            'complianceAlerts'     => $complianceAlerts,
            'companyProgress'  => $companyProgress,
            'companyPage'      => $companyPage,
            'companyPages'     => $companyPages,
            'companyTotal'     => $companyTotal,
            'dailyCompletions' => $dailyCompletions,
            'priorities'       => $priorities,

            'overdueTasksList'    => $overdueTasksList,
            'overdueInvoices'     => $overdueInvoices,
            'overdueInvoiceCount' => $overdueInvoiceCount,
            'financeRevenue'      => $financeRevenue,
            'financeExpenses'     => $monthlyExpenseTotal,
            'financeOutstanding'  => $financeOutstanding,
        ]);
    }

    private function currentWeekLabel(): string
    {
        $monday = strtotime('monday this week');
        $sunday = strtotime('sunday this week');

        return date('d/m/Y', $monday) . ' – ' . date('d/m/Y', $sunday);
    }

    /**
     * Genuine week-over-week delta: rows created in the trailing 7 days
     * vs the 7 days before that. Returns null when there's nothing in
     * the prior window to compare against (avoids a meaningless "+inf%").
     */
    private function weekOverWeek($model, string $dateField, array $extraWhere, ?int $companyScope): ?array
    {
        $build = function () use ($model, $extraWhere, $companyScope) {
            $q = clone $model;
            foreach ($extraWhere as $field => $value) {
                $q->where($field, $value);
            }
            if ($companyScope !== null) {
                $q->where('company_id', $companyScope);
            }

            return $q;
        };

        $thisWeek = $build()->where($dateField . ' >=', date('Y-m-d 00:00:00', strtotime('-6 days')))->countAllResults();
        $lastWeek = $build()
            ->where($dateField . ' >=', date('Y-m-d 00:00:00', strtotime('-13 days')))
            ->where($dateField . ' <', date('Y-m-d 00:00:00', strtotime('-6 days')))
            ->countAllResults();

        if ($lastWeek === 0) {
            return null;
        }

        $percent = (int) round((($thisWeek - $lastWeek) / $lastWeek) * 100);

        return ['percent' => $percent, 'direction' => $percent >= 0 ? 'up' : 'down'];
    }

    /**
     * Row counts per day for the trailing ('past') or coming ('future')
     * 7-day window, keyed in day order — feeds each stat card's sparkline
     * from that card's own real rows instead of a placeholder shape.
     */
    private function dailySeries($model, string $dateField, array $extraWhere, ?int $companyScope, string $direction): array
    {
        $q = clone $model;
        foreach ($extraWhere as $field => $value) {
            $q->where($field, $value);
        }
        if ($companyScope !== null) {
            $q->where('company_id', $companyScope);
        }

        $isDateOnly = in_array($dateField, ['meeting_date', 'due_date'], true);
        $dayExpr    = $isDateOnly ? $dateField : "DATE({$dateField})";

        if ($direction === 'past') {
            $q->where($dateField . ' >=', date('Y-m-d 00:00:00', strtotime('-6 days')));
        } else {
            $q->where($dateField . ' >=', date('Y-m-d'))
                ->where($dateField . ' <=', date('Y-m-d', strtotime('+6 days')));
        }

        $rows  = $q->select("{$dayExpr} as day, COUNT(*) as total")->groupBy('day')->findAll();
        $byDay = array_column($rows, 'total', 'day');

        $series = [];
        $range  = $direction === 'past' ? range(-6, 0) : range(0, 6);

        foreach ($range as $offset) {
            $day      = date('Y-m-d', strtotime(($offset >= 0 ? '+' : '') . $offset . ' days'));
            $series[] = (int) ($byDay[$day] ?? 0);
        }

        return $series;
    }

    private function relativeMeetingLabel(string $meetingDate, ?string $startTime): string
    {
        $timestamp = strtotime($meetingDate . ' ' . ($startTime ?? '00:00:00'));
        $diffHours = round(($timestamp - time()) / 3600);

        if ($diffHours <= 0) {
            return 'Now';
        }
        if ($diffHours < 24) {
            return 'In ' . $diffHours . ' hour' . ($diffHours === 1 ? '' : 's');
        }

        $diffDays = (int) round($diffHours / 24);

        return 'In ' . $diffDays . ' day' . ($diffDays === 1 ? '' : 's');
    }

    private function duePriority(string $dueDate): string
    {
        $daysAway = (strtotime($dueDate) - strtotime(date('Y-m-d'))) / 86400;

        if ($daysAway < 0) {
            return 'High';
        }
        if ($daysAway <= 3) {
            return 'Medium';
        }

        return 'Low';
    }

    private function urgentTasks(?int $userId, ?int $companyScope): array
    {
        $filters = [];
        if ($userId !== null) {
            $filters['assigned_to'] = $userId;
        }
        if ($companyScope !== null) {
            $filters['company_id'] = $companyScope;
        }

        return $this->taskModel->filtered($filters)
            ->whereNotIn('tasks.status', ['completed', 'cancelled'])
            ->where('tasks.due_date IS NOT NULL')
            ->orderBy('tasks.due_date', 'ASC')
            ->findAll(3);
    }

    /**
     * Merges the nearest-due item from each module the viewer can see
     * (tasks, compliance, leave approvals, meetings) into one ranked
     * list, sorted by how soon it's due — genuinely computed from each
     * module's own due date, not placeholder content.
     */
    private function buildPriorities(array $tasks, array $complianceAlerts, array $pendingLeave, array $meetings): array
    {
        $items = [];

        foreach ($tasks as $t) {
            $items[] = [
                'icon'     => 'fa-list-check',
                'color'    => 'purple',
                'title'    => $t['title'],
                'subtitle' => 'Due ' . date('d/m/Y, g:i A', strtotime($t['due_date'])),
                'priority' => $this->duePriority($t['due_date']),
                'sortKey'  => strtotime($t['due_date']),
                'link'     => site_url('tasks/' . $t['id']),
            ];
        }

        foreach (array_slice($complianceAlerts, 0, 2) as $c) {
            $items[] = [
                'icon'     => 'fa-clipboard-check',
                'color'    => 'red',
                'title'    => $c['title'] ?: $c['type_name'],
                'subtitle' => 'Due ' . date('d/m/Y', strtotime($c['due_date'])),
                'priority' => $c['priority'],
                'sortKey'  => strtotime($c['due_date']),
                'link'     => site_url('compliance/' . $c['id']),
            ];
        }

        foreach (array_slice($pendingLeave, 0, 1) as $l) {
            $items[] = [
                'icon'     => 'fa-plane-departure',
                'color'    => 'orange',
                'title'    => 'Review leave request — ' . $l['user_name'],
                'subtitle' => date('d/m/Y', strtotime($l['start_date'])) . ' – ' . date('d/m/Y', strtotime($l['end_date'])),
                'priority' => 'Medium',
                'sortKey'  => strtotime($l['created_at']),
                'link'     => site_url('hr/leave/' . $l['id']),
            ];
        }

        foreach (array_slice($meetings, 0, 1) as $m) {
            $items[] = [
                'icon'     => 'fa-handshake',
                'color'    => 'blue',
                'title'    => $m['title'],
                'subtitle' => $m['relative'],
                'priority' => 'Low',
                'sortKey'  => strtotime($m['meeting_date'] . ' ' . ($m['start_time'] ?? '00:00:00')),
                'link'     => site_url('meetings/' . $m['id']),
            ];
        }

        usort($items, static fn ($a, $b) => $a['sortKey'] <=> $b['sortKey']);

        return array_slice($items, 0, 4);
    }

    // The app has no shift/late policy, so "late" is a fixed cut-off on
    // the recorded check-in time rather than anything configurable.
    public const LATE_AFTER = '10:00:00';

    /**
     * HR Manager's own dashboard — workforce status, what needs action,
     * today's attendance, onboarding/offboarding progress and what's
     * coming up. Read-only queries against the existing HR/Onboarding/
     * Offboarding models, scoped to the HR user's company exactly like
     * those modules' own list pages (companyScopeFor).
     */
    private function hrDashboard()
    {
        $scope    = $this->companyScopeFor(self::COMPANY_SCOPED_ROLES);
        $company  = $scope !== null ? ['company_id' => $scope] : [];
        $today    = date('Y-m-d');
        $in30     = date('Y-m-d', strtotime('+30 days'));
        $db       = db_connect();

        $profileModel = new \App\Modules\HR\Models\EmployeeProfileModel();
        $leaveModel   = new LeaveRequestModel();

        // ---- Workforce ----
        $totalEmployees = $profileModel->filtered($company)->whereIn('employee_profiles.status', ['active', 'on_leave'])->countAllResults();
        $newJoiners     = $profileModel->filtered($company)
            ->where('employee_profiles.date_of_joining >=', date('Y-m-d', strtotime('-30 days')))
            ->where('employee_profiles.date_of_joining <=', $today)
            ->countAllResults();

        // ---- Today's attendance ----
        $attendanceToday = (new \App\Modules\HR\Models\AttendanceModel())
            ->filtered($company + ['date_from' => $today, 'date_to' => $today])->findAll();

        $present = $absent = $late = 0;
        foreach ($attendanceToday as $a) {
            if (in_array($a['status'], ['present', 'half_day'], true)) {
                $present++;
                if ($a['check_in'] && date('H:i:s', strtotime($a['check_in'])) > self::LATE_AFTER) {
                    $late++;
                }
            } elseif ($a['status'] === 'absent') {
                $absent++;
            }
        }

        $leaveToday = $leaveModel->filtered($company + ['status' => 'approved'])
            ->where('leave_requests.start_date <=', $today)
            ->where('leave_requests.end_date >=', $today)
            ->findAll();
        $onLeave   = count(array_unique(array_column($leaveToday, 'user_id')));
        $notMarked = max(0, $totalEmployees - $present - $absent - $onLeave);

        // ---- Onboarding ----
        $onbTaskModel = new \App\Modules\Onboarding\Models\OnboardingTaskModel();
        $onbActive    = (new \App\Modules\Onboarding\Models\OnboardingRecordModel())->filtered($company)
            ->whereNotIn('onboarding_records.status', ['confirmed', 'withdrawn'])
            ->findAll();

        if ($onbActive !== []) {
            $counts = $db->table('onboarding_tasks')
                ->select("onboarding_record_id, COUNT(*) as total, SUM(status = 'completed') as done")
                ->whereIn('onboarding_record_id', array_column($onbActive, 'id'))
                ->groupBy('onboarding_record_id')->get()->getResultArray();
            $byRecord = array_column($counts, null, 'onboarding_record_id');
            foreach ($onbActive as &$r) {
                $r['tasks_total'] = (int) ($byRecord[$r['id']]['total'] ?? 0);
                $r['tasks_done']  = (int) ($byRecord[$r['id']]['done'] ?? 0);
            }
            unset($r);
        }

        // ---- Offboarding ----
        $offActive = (new \App\Modules\Offboarding\Models\OffboardingRecordModel())->filtered($company)
            ->whereIn('offboarding_records.status', \App\Modules\Offboarding\Models\OffboardingRecordModel::ACTIVE_STATUSES)
            ->findAll();

        // ---- Action required ----
        $pendingOnbTasks = $onbTaskModel->pendingForRole('hr', $scope);
        $pendingOffTasks = (new \App\Modules\Offboarding\Models\OffboardingTaskModel())->pendingForRole('hr', $scope);

        // Only requests HR is actually allowed to act on (same rank rule
        // LeaveController::approve enforces), not every pending request.
        $approvable = array_keys(array_filter(
            \App\Modules\HR\Controllers\LeaveController::ROLE_RANK,
            static fn ($rank) => $rank > \App\Modules\HR\Controllers\LeaveController::ROLE_RANK['hr']
        ));
        $pendingLeave = $leaveModel->filtered($company + ['status' => 'pending', 'requester_role_slugs' => $approvable])->findAll();

        // "Attendance issues": someone marked absent today, or a past
        // check-in in the last 7 days that never got a check-out.
        $attendanceIssues = [];
        foreach ($attendanceToday as $a) {
            if ($a['status'] === 'absent') {
                $attendanceIssues[] = ['name' => $a['user_name'], 'detail' => 'Absent today'];
            }
        }
        $recent = (new \App\Modules\HR\Models\AttendanceModel())
            ->filtered($company + ['date_from' => date('Y-m-d', strtotime('-7 days')), 'date_to' => date('Y-m-d', strtotime('-1 day')), 'status' => 'present'])->findAll();
        foreach ($recent as $a) {
            if ($a['check_in'] && ! $a['check_out']) {
                $attendanceIssues[] = ['name' => $a['user_name'], 'detail' => 'No check-out on ' . date('d/m/Y', strtotime($a['date']))];
            }
        }

        // ---- Upcoming (next 30 days) ----
        $joinings = array_values(array_filter($onbActive, static fn ($r) => $r['joining_date'] && $r['joining_date'] >= $today && $r['joining_date'] <= $in30));
        usort($joinings, static fn ($a, $b) => $a['joining_date'] <=> $b['joining_date']);

        $lastDays = array_values(array_filter($offActive, static fn ($r) => $r['last_working_day'] && $r['last_working_day'] >= $today && $r['last_working_day'] <= $in30));
        usort($lastDays, static fn ($a, $b) => $a['last_working_day'] <=> $b['last_working_day']);

        $birthdays = [];
        $withDob = $profileModel->filtered($company)->whereIn('employee_profiles.status', ['active', 'on_leave'])
            ->where('employee_profiles.date_of_birth IS NOT NULL')->findAll();
        foreach ($withDob as $e) {
            $next = date('Y') . substr($e['date_of_birth'], 4);
            if ($next < $today) {
                $next = (date('Y') + 1) . substr($e['date_of_birth'], 4);
            }
            if ($next <= $in30) {
                $birthdays[] = ['name' => $e['user_name'], 'date' => $next];
            }
        }
        usort($birthdays, static fn ($a, $b) => $a['date'] <=> $b['date']);

        $upcomingLeave = $leaveModel->filtered($company + ['status' => 'approved'])
            ->where('leave_requests.start_date >', $today)
            ->where('leave_requests.start_date <=', $in30)
            ->findAll();
        usort($upcomingLeave, static fn ($a, $b) => $a['start_date'] <=> $b['start_date']);

        return view('App\Modules\Dashboard\hr_index', [
            'title'          => 'Dashboard',
            'navActive'      => 'dashboard',
            'weekRangeLabel' => $this->currentWeekLabel(),
            'noCompanyAssigned' => $scope === 0,

            'totalEmployees' => $totalEmployees,
            'presentToday'   => $present,
            'onLeaveToday'   => $onLeave,
            'newJoiners'     => $newJoiners,
            'exitsInProgress' => count($offActive),
            'absentToday'    => $absent,
            'lateToday'      => $late,
            'notMarked'      => $notMarked,
            'lateAfter'      => date('g:i A', strtotime(self::LATE_AFTER)),

            'onbActive'      => $onbActive,
            'offActive'      => $offActive,
            'pendingOnbTasks' => $pendingOnbTasks,
            'pendingOffTasks' => $pendingOffTasks,
            'pendingLeave'   => $pendingLeave,
            'attendanceIssues' => $attendanceIssues,

            'joinings'       => $joinings,
            'lastDays'       => $lastDays,
            'birthdays'      => $birthdays,
            'upcomingLeave'  => $upcomingLeave,
        ]);
    }
}
