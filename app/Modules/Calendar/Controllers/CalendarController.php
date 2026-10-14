<?php

namespace App\Modules\Calendar\Controllers;

use App\Controllers\BaseController;
use App\Modules\Company\Models\CompanyModel;
use App\Modules\Compliance\Models\ComplianceItemModel;
use App\Modules\HR\Models\AttendanceModel;
use App\Modules\Meeting\Models\MeetingModel;
use App\Modules\Task\Models\TaskModel;

/**
 * Aggregates Task due dates, Meetings, and Compliance due dates into a
 * single FullCalendar view — no new tables, just a read-only overlay
 * on data the Task/Meeting/Compliance modules already own. Respects
 * the same per-module view permission and Employee personal-scoping
 * (assigned/participant only) as the Dashboard and each module's own
 * list page.
 */
class CalendarController extends BaseController
{
    protected TaskModel $taskModel;
    protected MeetingModel $meetingModel;
    protected ComplianceItemModel $complianceModel;
    protected CompanyModel $companyModel;
    protected AttendanceModel $attendanceModel;

    public function __construct()
    {
        $this->taskModel       = new TaskModel();
        $this->meetingModel    = new MeetingModel();
        $this->complianceModel = new ComplianceItemModel();
        $this->companyModel    = new CompanyModel();
        $this->attendanceModel = new AttendanceModel();
    }

    public function index()
    {
        return view('App\Modules\Calendar\index', [
            'title'     => 'Calendar',
            'navActive' => 'calendar',
            'companies' => $this->scopedCompanyOptions($this->companyModel->optionsList()),

            'canViewTasks'      => can('task.view'),
            'canViewMeetings'   => can('meeting.view'),
            'canViewCompliance' => can('compliance.view'),
            'canViewWeekHours'  => can('attendance.view'),
        ]);
    }

    /**
     * JSON weekly work-hours view for the current user — "how many
     * hours did I work this week, and on which days" — reusing the same
     * check_in/check_out data and hours math the Attendance History page
     * already shows per-row, just rolled up into a Mon-Sun week. Always
     * scoped to the logged-in user (this is "my week", same as the
     * Dashboard's own personal widgets), never another employee's.
     */
    public function weekHours()
    {
        if (! can('attendance.view')) {
            return $this->response->setJSON(['days' => [], 'totalMinutes' => 0]);
        }

        $userId = (int) session('userId');
        $anchor = $this->request->getGet('week') ?: date('Y-m-d');
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $anchor)) {
            $anchor = date('Y-m-d');
        }

        // ISO week starts Monday — walk back to it from whatever date
        // the caller anchors on ('N' = 1 for Monday .. 7 for Sunday).
        $monday = date('Y-m-d', strtotime($anchor . ' -' . (date('N', strtotime($anchor)) - 1) . ' days'));

        $days  = $this->attendanceModel->weekSummary($userId, $monday);
        $total = array_sum(array_column($days, 'minutes'));

        return $this->response->setJSON([
            'weekStart'    => $monday,
            'weekEnd'      => date('Y-m-d', strtotime($monday . ' +6 days')),
            'days'         => $days,
            'totalMinutes' => $total,
        ]);
    }

    /**
     * JSON event feed consumed by FullCalendar's `events` callback.
     */
    public function events()
    {
        $isPersonalScope = session('roleSlug') === 'employee';
        $userId           = (int) session('userId');
        $companyId        = $this->request->getGet('company_id');

        // Company Admin can't widen the calendar to another company by
        // picking it from the dropdown — same restriction as the
        // Tasks/Meetings list pages themselves.
        $companyScope = $this->companyScopeFor(self::COMPANY_SCOPED_ROLES);
        if ($companyScope !== null) {
            $companyId = $companyScope;
        }

        $events = [];

        if (can('task.view')) {
            $filters = array_filter(['assigned_to' => $isPersonalScope ? $userId : null]);
            // array_filter() would drop company_id if it's 0 (unprovisioned
            // Company Admin) as falsy — merged in after, same convention
            // used everywhere else companyScopeFor() feeds a filter array.
            if ($companyId !== null && $companyId !== '') {
                $filters['company_id'] = $companyId;
            }
            foreach ($this->taskModel->filtered($filters)->findAll() as $task) {
                if (! $task['due_date']) {
                    continue;
                }

                $overdue    = $task['due_date'] < date('Y-m-d') && ! in_array($task['status'], ['completed', 'cancelled'], true);
                $importance = $overdue ? 0 : (in_array($task['priority'], ['urgent', 'high'], true) ? 1 : 2);

                // Tasks only carry a due DATE, not a time — kept all-day
                // rather than assigned a synthetic time, since a made-up
                // time slot in the Week view would look like a real
                // scheduled time and mislead the reader.
                $events[] = [
                    'id'    => 'task-' . $task['id'],
                    'title' => $task['title'],
                    'start' => $task['due_date'],
                    'allDay' => true,
                    'url'   => site_url('tasks/' . $task['id']),
                    'color' => $overdue ? '#cc1f2c' : '#5b6472',
                    'extendedProps' => ['type' => 'Task', 'importance' => $importance],
                ];
            }
        }

        if (can('meeting.view')) {
            $filters = array_filter(['participant_user_id' => $isPersonalScope ? $userId : null]);
            if ($companyId !== null && $companyId !== '') {
                $filters['company_id'] = $companyId;
            }

            foreach ($this->meetingModel->filtered($filters)->findAll() as $meeting) {
                $events[] = [
                    'id'     => 'meeting-' . $meeting['id'],
                    'title'  => $meeting['title'],
                    'start'  => $meeting['meeting_date'] . ($meeting['start_time'] ? 'T' . $meeting['start_time'] : ''),
                    'allDay' => ! $meeting['start_time'],
                    'url'    => site_url('meetings/' . $meeting['id']),
                    'color'  => '#2563eb',
                    'extendedProps' => ['type' => 'Meeting', 'importance' => 1],
                ];
            }
        }

        if (can('compliance.view')) {
            $filters = array_filter([
                'company_id'          => $companyId,
                'responsible_user_id' => $isPersonalScope ? $userId : null,
            ]);
            foreach ($this->complianceModel->filtered($filters)->findAll() as $item) {
                $overdue = $item['status'] === 'overdue';

                // Same as tasks — compliance items only carry a due DATE,
                // kept all-day rather than a fabricated time.
                $events[] = [
                    'id'     => 'compliance-' . $item['id'],
                    'title'  => $item['title'] ?: $item['type_name'],
                    'start'  => $item['due_date'],
                    'allDay' => true,
                    'url'    => site_url('compliance/' . $item['id']),
                    'color'  => $overdue ? '#cc1f2c' : '#d97706',
                    'extendedProps' => ['type' => 'Compliance', 'importance' => $overdue ? 0 : 2],
                ];
            }
        }

        return $this->response->setJSON($events);
    }
}
