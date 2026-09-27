<?php

namespace App\Modules\Notification\Services;

use App\Modules\Compliance\Models\ComplianceItemModel;
use App\Modules\Expense\Models\ExpenseModel;
use App\Modules\Meeting\Models\MeetingModel;
use App\Modules\Meeting\Models\MeetingParticipantModel;
use App\Modules\Notification\Models\NotificationModel;
use App\Modules\Task\Models\TaskModel;
use App\Modules\Website\Models\WebsiteModel;

/**
 * Two ways notifications get created:
 *  - notify(): called directly by other modules' controllers at the
 *    moment something happens (task assigned, meeting invite sent).
 *  - sweepFor(): a due-date scan triggered from the navbar bell's fetch
 *    (see Notification/Controllers/NotificationController::recent),
 *    same lazy-refresh pattern as ComplianceItemModel::refreshOverdueStatuses.
 *    Throttled to once per SWEEP_THROTTLE_SECONDS per session — it was
 *    originally re-run on every single page load, but that meant ~6
 *    extra queries (plus an existsToday() check per matching row) on
 *    every navigation even though the underlying data rarely changes
 *    between clicks. A session timestamp skips the rescan until it's
 *    actually due again.
 */
class NotificationService
{
    private const SWEEP_THROTTLE_SECONDS = 300;

    protected NotificationModel $notificationModel;
    protected TaskModel $taskModel;
    protected ComplianceItemModel $complianceModel;
    protected MeetingModel $meetingModel;
    protected MeetingParticipantModel $participantModel;
    protected ExpenseModel $expenseModel;
    protected WebsiteModel $websiteModel;

    public function __construct()
    {
        $this->notificationModel = new NotificationModel();
        $this->taskModel         = new TaskModel();
        $this->complianceModel   = new ComplianceItemModel();
        $this->meetingModel      = new MeetingModel();
        $this->participantModel  = new MeetingParticipantModel();
        $this->expenseModel      = new ExpenseModel();
        $this->websiteModel      = new WebsiteModel();
    }

    public function notify(int $userId, string $type, string $title, string $message, ?string $module = null, ?int $relatedId = null): void
    {
        $this->notificationModel->create($userId, $type, $title, $message, $module, $relatedId);
    }

    public function sweepFor(int $userId): void
    {
        $lastSweptAt = session('notif_swept_at');

        if ($lastSweptAt !== null && (time() - $lastSweptAt) < self::SWEEP_THROTTLE_SECONDS) {
            return;
        }

        $this->sweepTasks($userId);
        $this->sweepCompliance($userId);
        $this->sweepMeetingsToday($userId);
        $this->sweepExpenses($userId);
        $this->sweepWebsites($userId);

        session()->set('notif_swept_at', time());
    }

    private function sweepTasks(int $userId): void
    {
        $today  = date('Y-m-d');
        $window = date('Y-m-d', strtotime('+2 days'));

        $tasks = $this->taskModel
            ->where('assigned_to', $userId)
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->where('due_date IS NOT NULL')
            ->where('due_date <=', $window)
            ->findAll();

        foreach ($tasks as $task) {
            $overdue = $task['due_date'] < $today;
            $type    = $overdue ? 'task_overdue' : 'task_due_soon';

            if ($this->notificationModel->existsToday($userId, $type, 'task', (int) $task['id'])) {
                continue;
            }

            $this->notify(
                $userId,
                $type,
                ($overdue ? 'Overdue: ' : 'Due soon: ') . $task['title'],
                'Task "' . $task['title'] . '" ' . ($overdue ? 'is overdue' : 'is due soon') . ' (due ' . $task['due_date'] . ').',
                'task',
                (int) $task['id']
            );
        }
    }

    private function sweepCompliance(int $userId): void
    {
        $today = date('Y-m-d');

        $items = $this->complianceModel
            ->where('responsible_user_id', $userId)
            ->whereIn('status', ['pending', 'in_progress', 'overdue'])
            ->findAll();

        foreach ($items as $item) {
            $daysUntilDue = (strtotime($item['due_date']) - strtotime($today)) / 86400;
            $reminderDays = $item['reminder_days_before'] ?? 7;

            if ($daysUntilDue > $reminderDays) {
                continue;
            }

            $overdue = $item['due_date'] < $today;
            $type    = $overdue ? 'compliance_overdue' : 'compliance_due_soon';

            if ($this->notificationModel->existsToday($userId, $type, 'compliance', (int) $item['id'])) {
                continue;
            }

            $this->notify(
                $userId,
                $type,
                ($overdue ? 'Overdue: ' : 'Due soon: ') . $item['title'],
                'Compliance item "' . $item['title'] . '" ' . ($overdue ? 'is overdue' : 'is due soon') . ' (due ' . $item['due_date'] . ').',
                'compliance',
                (int) $item['id']
            );
        }
    }

    private function sweepMeetingsToday(int $userId): void
    {
        $today       = date('Y-m-d');
        $meetingIds  = $this->participantModel->where('user_id', $userId)->findColumn('meeting_id');

        if (empty($meetingIds)) {
            return;
        }

        $meetings = $this->meetingModel->whereIn('id', $meetingIds)->where('meeting_date', $today)->findAll();

        foreach ($meetings as $meeting) {
            if ($this->notificationModel->existsToday($userId, 'meeting_today', 'meeting', (int) $meeting['id'])) {
                continue;
            }

            $this->notify(
                $userId,
                'meeting_today',
                'Today: ' . $meeting['title'],
                'Meeting "' . $meeting['title'] . '" is today' . ($meeting['start_time'] ? ' at ' . $meeting['start_time'] : '') . '.',
                'meeting',
                (int) $meeting['id']
            );
        }
    }

    private function sweepExpenses(int $userId): void
    {
        $today = date('Y-m-d');

        $expenses = $this->expenseModel
            ->where('status', 'active')
            ->where('created_by', $userId)
            ->where('renewal_date IS NOT NULL')
            ->where('renewal_date <=', date('Y-m-d', strtotime('+7 days')))
            ->findAll();

        foreach ($expenses as $expense) {
            $overdue = $expense['renewal_date'] < $today;
            $type    = $overdue ? 'expense_overdue' : 'expense_due_soon';

            if ($this->notificationModel->existsToday($userId, $type, 'expense', (int) $expense['id'])) {
                continue;
            }

            $this->notify(
                $userId,
                $type,
                ($overdue ? 'Renewal overdue: ' : 'Renewal due soon: ') . $expense['vendor'],
                'The "' . $expense['vendor'] . '" subscription ' . ($overdue ? 'was due to renew' : 'renews') . ' on ' . $expense['renewal_date'] . '.',
                'expense',
                (int) $expense['id']
            );
        }
    }

    private function sweepWebsites(int $userId): void
    {
        $today = date('Y-m-d');
        $soon  = date('Y-m-d', strtotime('+30 days'));

        $sites = $this->websiteModel
            ->where('status !=', 'expired')
            ->where('created_by', $userId)
            ->groupStart()
                ->where('ssl_expiry IS NOT NULL')->where('ssl_expiry <=', $soon)
                ->orGroupStart()
                    ->where('renewal_date IS NOT NULL')->where('renewal_date <=', $soon)
                ->groupEnd()
            ->groupEnd()
            ->findAll();

        foreach ($sites as $site) {
            $sslDue     = $site['ssl_expiry'] && $site['ssl_expiry'] <= $soon;
            $renewalDue = $site['renewal_date'] && $site['renewal_date'] <= $soon;
            $overdue    = ($site['ssl_expiry'] && $site['ssl_expiry'] < $today) || ($site['renewal_date'] && $site['renewal_date'] < $today);

            $reason = $sslDue && $renewalDue ? 'SSL and renewal' : ($sslDue ? 'SSL' : 'renewal');
            $type   = $overdue ? 'website_overdue' : 'website_due_soon';

            if ($this->notificationModel->existsToday($userId, $type, 'website', (int) $site['id'])) {
                continue;
            }

            $this->notify(
                $userId,
                $type,
                ($overdue ? 'Overdue: ' : 'Due soon: ') . $site['domain'],
                $reason . ' for "' . $site['domain'] . '" ' . ($overdue ? 'has passed' : 'is coming up') . '.',
                'website',
                (int) $site['id']
            );
        }
    }
}
