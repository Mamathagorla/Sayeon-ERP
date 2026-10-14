<?php

namespace App\Modules\Meeting\Models;

use CodeIgniter\Model;
use DateTime;

/**
 * "Automatic meeting creation based on time" — the recurrence template.
 * Mirrors RecurringExpenseModel's shape closely (see its own docblocks
 * for the reasoning); the only real difference is the frequency set,
 * since meetings recur on shorter cycles than expenses.
 */
class RecurringMeetingModel extends Model
{
    public const FREQUENCIES = ['daily', 'weekly', 'monthly'];
    public const END_TYPES   = ['occurrences', 'end_date'];
    public const STATUSES    = ['active', 'paused', 'completed', 'cancelled'];

    // Same reasoning as RecurringExpenseModel::MAX_OCCURRENCES — a
    // generous but finite cap on a single template's total occurrences.
    // 365 daily occurrences is a full year at the shortest frequency.
    private const MAX_OCCURRENCES = 365;

    protected $table         = 'recurring_meetings';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = [
        'company_id', 'title', 'agenda', 'location', 'meeting_link', 'start_time', 'end_time',
        'ends_next_day', 'timezone',
        'frequency', 'start_date', 'end_type', 'end_date', 'occurrences_total',
        'occurrences_generated', 'next_generation_date', 'status', 'created_by',
    ];

    // Hard ceiling on how far ahead the upcoming-date scan will walk
    // (a daily template started years ago would otherwise need thousands
    // of steps to reach today). Far above any real backlog.
    private const MAX_SCAN_STEPS = 10000;

    /**
     * Validates a template's time window. Times arrive as "HH:MM" from
     * <input type="time"> (always 24-hour, whatever the browser displays).
     * An overnight meeting must be ticked explicitly: without ends_next_day
     * the end must be strictly after the start; with it, the end must be
     * strictly before the start (the meeting finishes the following day).
     * Returns a user-facing error, or null when the window is valid.
     */
    public function timeRangeError(?string $start, ?string $end, bool $endsNextDay): ?string
    {
        if ($start === null && $end === null) {
            return $endsNextDay ? 'Set a start and end time to use "Ends the next day".' : null;
        }
        if ($start === null || $end === null) {
            return 'Set both a start and an end time, or leave both empty.';
        }

        $startMin = self::minutes($start);
        $endMin   = self::minutes($end);

        if ($endsNextDay) {
            return $endMin < $startMin
                ? null
                : '"Ends the next day" needs an end time earlier than the start time (for example 11:30 PM to 12:30 AM).';
        }

        return $endMin > $startMin
            ? null
            : 'End time must be after start time. If the meeting runs past midnight, tick "Ends the next day".';
    }

    public static function minutes(string $hhmm): int
    {
        [$h, $m] = array_map('intval', explode(':', substr($hhmm, 0, 5)));

        return $h * 60 + $m;
    }

    /**
     * The template's wall-clock time in the organization's time format. A
     * template time is not tied to a date, so it is shown as entered (not
     * converted to the viewer's timezone).
     */
    public static function formatTime(?string $time): string
    {
        return erp_time($time);
    }

    /**
     * The next occurrence date that has not been generated yet and is not
     * before today, walking the schedule forward from the last-generated
     * pointer. Respects the template's end (occurrence cap or end date).
     * Returns null for templates that are cancelled/completed, or whose
     * schedule has no more occurrences. This is what the list shows as
     * "Next Occurrence", so it stays correct even when the daily generator
     * has not run recently or a template's pointer is behind.
     */
    public function nextOccurrenceDate(array $template, ?string $today = null): ?string
    {
        if (! in_array($template['status'], ['active', 'paused'], true) || $template['next_generation_date'] === null) {
            return null;
        }

        $today  = $today ?? date('Y-m-d');
        $date   = $template['next_generation_date'];
        $number = (int) $template['occurrences_generated'] + 1;

        for ($step = 0; $date < $today; $step++) {
            if ($step >= self::MAX_SCAN_STEPS) {
                return null;
            }
            $date = $this->advanceDate($date, $template['frequency']);
            $number++;
        }

        if ($template['end_type'] === 'occurrences' && $template['occurrences_total'] !== null && $number > (int) $template['occurrences_total']) {
            return null;
        }
        if ($template['end_type'] === 'end_date' && $template['end_date'] !== null && $date > $template['end_date']) {
            return null;
        }

        return $date;
    }

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'company_id'        => 'required|integer',
        'title'             => 'required|min_length[2]|max_length[200]',
        'frequency'         => 'required|in_list[daily,weekly,monthly]',
        'start_date'        => 'required|valid_date',
        'end_type'          => 'required|in_list[occurrences,end_date]',
        'end_date'          => 'permit_empty|valid_date',
        'occurrences_total' => 'permit_empty|integer|greater_than[0]|less_than_equal_to[' . self::MAX_OCCURRENCES . ']',
    ];

    public function filtered(array $filters = [])
    {
        $builder = $this->select('recurring_meetings.*, companies.name as company_name')
            ->join('companies', 'companies.id = recurring_meetings.company_id');

        if (array_key_exists('company_id', $filters)) {
            $builder->where('recurring_meetings.company_id', $filters['company_id']);
        }
        if (! empty($filters['status'])) {
            $builder->where('recurring_meetings.status', $filters['status']);
        }

        return $builder->orderBy('recurring_meetings.created_at', 'DESC');
    }

    /**
     * Every active template whose next occurrence is already due — what
     * RecurringMeetingGenerator processes.
     */
    public function dueForGeneration(?int $companyId = null): array
    {
        $builder = $this->where('status', 'active')
            ->where('next_generation_date IS NOT NULL')
            ->where('next_generation_date <=', date('Y-m-d'));

        if ($companyId !== null) {
            $builder->where('company_id', $companyId);
        }

        return $builder->findAll();
    }

    /**
     * Advances a date by one cycle. Only 'monthly' needs day-of-month
     * clamping (RecurringExpenseModel::advanceDate()'s Jan-31 gotcha);
     * daily/weekly are always a fixed number of days.
     */
    public function advanceDate(string $date, string $frequency): string
    {
        if ($frequency === 'daily') {
            return (new DateTime($date))->modify('+1 day')->format('Y-m-d');
        }
        if ($frequency === 'weekly') {
            return (new DateTime($date))->modify('+7 days')->format('Y-m-d');
        }

        $dt  = new DateTime($date);
        $day = (int) $dt->format('d');

        $dt->modify('first day of this month');
        $dt->modify('+1 month');

        $lastDayOfTargetMonth = (int) $dt->format('t');
        $dt->setDate((int) $dt->format('Y'), (int) $dt->format('m'), min($day, $lastDayOfTargetMonth));

        return $dt->format('Y-m-d');
    }
}
