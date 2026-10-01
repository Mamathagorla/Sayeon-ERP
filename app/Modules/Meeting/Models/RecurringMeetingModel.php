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
        'frequency', 'start_date', 'end_type', 'end_date', 'occurrences_total',
        'occurrences_generated', 'next_generation_date', 'status', 'created_by',
    ];

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
