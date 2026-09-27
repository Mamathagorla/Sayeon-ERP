<?php

namespace App\Modules\Expense\Models;

use CodeIgniter\Model;
use DateTime;

class RecurringExpenseModel extends Model
{
    public const FREQUENCIES = ['monthly', 'quarterly', 'yearly'];
    public const END_TYPES   = ['occurrences', 'end_date'];
    public const STATUSES    = ['active', 'paused', 'completed', 'cancelled'];

    protected $table         = 'recurring_expenses';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = [
        'company_id', 'title', 'category', 'amount', 'description', 'frequency',
        'start_date', 'end_type', 'end_date', 'occurrences_total', 'occurrences_generated',
        'next_generation_date', 'status', 'created_by',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    // Same DECIMAL(12,2) ceiling as ExpenseModel::MAX_AMOUNT — each
    // generated occurrence carries this same amount, so it can't
    // physically exceed the column either.
    private const MAX_AMOUNT = 9999999999.99;

    // 240 monthly occurrences is 20 years — generous headroom for a
    // fat-fingered "custom number of occurrences" while still capping
    // how much a single template can ever queue up.
    private const MAX_OCCURRENCES = 240;

    protected $validationRules = [
        'company_id'         => 'required|integer',
        'title'               => 'required|min_length[2]|max_length[150]',
        'category'            => 'required|max_length[100]',
        'amount'              => 'required|decimal|greater_than[0]|less_than_equal_to[' . self::MAX_AMOUNT . ']',
        'frequency'           => 'required|in_list[monthly,quarterly,yearly]',
        'start_date'          => 'required|valid_date',
        'end_type'            => 'required|in_list[occurrences,end_date]',
        'end_date'            => 'permit_empty|valid_date',
        'occurrences_total'   => 'permit_empty|integer|greater_than[0]|less_than_equal_to[' . self::MAX_OCCURRENCES . ']',
    ];

    public function filtered(array $filters = [])
    {
        $builder = $this->select('recurring_expenses.*, companies.name as company_name')
            ->join('companies', 'companies.id = recurring_expenses.company_id');

        // array_key_exists (not empty()) — same convention as
        // ExpenseModel::filtered(): 0 must match zero rows.
        if (array_key_exists('company_id', $filters)) {
            $builder->where('recurring_expenses.company_id', $filters['company_id']);
        }
        if (! empty($filters['status'])) {
            $builder->where('recurring_expenses.status', $filters['status']);
        }

        return $builder->orderBy('recurring_expenses.created_at', 'DESC');
    }

    /**
     * Every active template whose next occurrence is already due —
     * what RecurringExpenseGenerator processes. Optionally narrowed to
     * one company for a scoped "Generate Now" click.
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
     * A read-only projection of the next $periods occurrences — no DB
     * writes, nothing generated. This is what answers "what will the
     * next 2/3 months of this expense look like", separately from
     * RecurringExpenseGenerator actually creating them once their date
     * arrives. Mirrors RecurringExpenseGenerator::runOne()'s exact
     * stop-condition logic (check the *advanced* date against the
     * limit, not the current one) so a forecast and a real run always
     * agree on where a template would actually stop.
     */
    public function forecast(array $template, int $periods): array
    {
        $rows = [];

        if ($template['status'] !== 'active' || $template['next_generation_date'] === null) {
            return $rows;
        }

        $date       = $template['next_generation_date'];
        $occurrence = (int) $template['occurrences_generated'];

        for ($i = 0; $i < $periods; $i++) {
            $occurrence++;
            $rows[] = [
                'occurrence_number' => $occurrence,
                'date'              => $date,
                'amount'            => (float) $template['amount'],
            ];

            $nextDate = $this->advanceDate($date, $template['frequency']);

            $reachedOccurrenceLimit = $template['end_type'] === 'occurrences'
                && $template['occurrences_total'] !== null
                && $occurrence >= (int) $template['occurrences_total'];

            $reachedEndDate = $template['end_type'] === 'end_date'
                && $template['end_date'] !== null
                && $nextDate > $template['end_date'];

            if ($reachedOccurrenceLimit || $reachedEndDate) {
                break;
            }

            $date = $nextDate;
        }

        return $rows;
    }

    /**
     * Advances a date by one billing period, clamping the day-of-month
     * so e.g. Jan 31 + 1 month lands on Feb 28/29 instead of overflowing
     * into March (PHP's `strtotime('+1 month')` gotcha on month-end
     * dates) — matters here because rent/subscription-style recurring
     * expenses are exactly the case most likely to start on a 29th-31st.
     */
    public function advanceDate(string $date, string $frequency): string
    {
        $months = match ($frequency) {
            'quarterly' => 3,
            'yearly'    => 12,
            default     => 1,
        };

        $dt  = new DateTime($date);
        $day = (int) $dt->format('d');

        $dt->modify('first day of this month');
        $dt->modify("+{$months} month");

        $lastDayOfTargetMonth = (int) $dt->format('t');
        $dt->setDate((int) $dt->format('Y'), (int) $dt->format('m'), min($day, $lastDayOfTargetMonth));

        return $dt->format('Y-m-d');
    }
}
