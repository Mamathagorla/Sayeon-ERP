<?php

namespace App\Modules\Expense\Models;

use CodeIgniter\Model;

class ExpenseModel extends Model
{
    public const BILLING_CYCLES = ['monthly', 'quarterly', 'yearly', 'one_time'];
    public const STATUSES       = ['active', 'cancelled'];

    protected $table         = 'expenses';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = [
        'company_id', 'vendor', 'category', 'billing_cycle', 'amount',
        'renewal_date', 'auto_renewal', 'payment_method', 'notes', 'status', 'created_by',
        // Set only by RecurringExpenseGenerator — null for every
        // manually-created expense. ExpenseController::payload() never
        // populates these, so the normal create/edit form can't touch
        // them even though they're mass-assignable here.
        'recurring_expense_id', 'occurrence_number',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    // Upper bound matches the DECIMAL(12,2) column — the field can't
    // physically hold more than this, so reject it before the DB does.
    private const MAX_AMOUNT = 9999999999.99;

    protected $validationRules = [
        'company_id'    => 'required|integer',
        'vendor'        => 'required|min_length[2]|max_length[150]',
        'category'      => 'required|max_length[100]',
        'billing_cycle' => 'required|in_list[monthly,quarterly,yearly,one_time]',
        'amount'        => 'required|decimal|greater_than_equal_to[0]|less_than_equal_to[' . self::MAX_AMOUNT . ']',
    ];

    /**
     * The generated-expense history for one recurring template — feeds
     * RecurringExpenseController::show().
     */
    public function generatedFor(int $recurringExpenseId): array
    {
        return $this->where('recurring_expense_id', $recurringExpenseId)
            ->orderBy('occurrence_number', 'ASC')
            ->findAll();
    }

    public function filtered(array $filters = [])
    {
        $builder = $this->select('expenses.*, companies.name as company_name')
            ->join('companies', 'companies.id = expenses.company_id');

        // array_key_exists (not empty()) — 0 means "Company Admin has no
        // employee_profiles company assigned yet" and must match zero
        // rows, not fall through to showing every company's expenses.
        if (array_key_exists('company_id', $filters)) {
            $builder->where('expenses.company_id', $filters['company_id']);
        }
        if (! empty($filters['category'])) {
            $builder->where('expenses.category', $filters['category']);
        }
        if (! empty($filters['status'])) {
            $builder->where('expenses.status', $filters['status']);
        }

        return $builder->orderBy('expenses.renewal_date', 'ASC');
    }

    /**
     * Rough monthly-equivalent cost — normalizes quarterly/yearly amounts
     * down to a per-month figure so the Dashboard's "Monthly Expenses"
     * card can add unlike billing cycles together meaningfully.
     */
    public function monthlyEquivalentTotal(?int $companyId = null): float
    {
        $builder = $this->where('status', 'active')->where('billing_cycle !=', 'one_time');

        if ($companyId !== null) {
            $builder->where('company_id', $companyId);
        }

        $total = 0.0;

        foreach ($builder->findAll() as $expense) {
            $total += match ($expense['billing_cycle']) {
                'monthly'   => (float) $expense['amount'],
                'quarterly' => (float) $expense['amount'] / 3,
                'yearly'    => (float) $expense['amount'] / 12,
                default     => 0.0,
            };
        }

        return round($total, 2);
    }

    /**
     * Active subscriptions renewing within N days or already past their
     * renewal date — feeds both the list page's "due soon" flag and the
     * notification sweep.
     */
    public function renewingSoon(int $withinDays = 14): array
    {
        return $this->where('status', 'active')
            ->where('renewal_date IS NOT NULL')
            ->where('renewal_date <=', date('Y-m-d', strtotime("+{$withinDays} days")))
            ->findAll();
    }

    /**
     * Monthly-equivalent total "as of" a given date — same normalization
     * as monthlyEquivalentTotal(), scoped to expenses recorded by then.
     * There's no historical status ledger (a cancelled expense doesn't
     * record when it stopped), so this is a best-effort snapshot: it
     * reads "active" as of today, not as of the requested date. Good
     * enough for a trend across recent months; treat older/cancelled
     * history with that caveat in mind.
     */
    public function monthlyEquivalentAsOf(string $asOfDate, ?int $companyId = null): float
    {
        $builder = $this->where('status', 'active')
            ->where('billing_cycle !=', 'one_time')
            ->where('created_at <=', $asOfDate);

        if ($companyId !== null) {
            $builder->where('company_id', $companyId);
        }

        $total = 0.0;

        foreach ($builder->findAll() as $expense) {
            $total += match ($expense['billing_cycle']) {
                'monthly'   => (float) $expense['amount'],
                'quarterly' => (float) $expense['amount'] / 3,
                'yearly'    => (float) $expense['amount'] / 12,
                default     => 0.0,
            };
        }

        return round($total, 2);
    }

    /**
     * Same snapshot as monthlyEquivalentAsOf(), broken out per category
     * instead of summed into one figure — what the Profit & Loss report's
     * per-quarter "EXPENSES" line items reuse, so each category's
     * quarterly figure is exactly 3x this same monthly-equivalent number
     * (never a different, disconnected calculation).
     *
     * @return array<string, float> category name => monthly-equivalent total
     */
    public function monthlyEquivalentByCategoryAsOf(string $asOfDate, ?int $companyId = null): array
    {
        $builder = $this->where('status', 'active')
            ->where('billing_cycle !=', 'one_time')
            ->where('created_at <=', $asOfDate);

        if ($companyId !== null) {
            $builder->where('company_id', $companyId);
        }

        $byCategory = [];

        foreach ($builder->findAll() as $expense) {
            $monthly = match ($expense['billing_cycle']) {
                'monthly'   => (float) $expense['amount'],
                'quarterly' => (float) $expense['amount'] / 3,
                'yearly'    => (float) $expense['amount'] / 12,
                default     => 0.0,
            };

            $byCategory[$expense['category']] = ($byCategory[$expense['category']] ?? 0.0) + $monthly;
        }

        return array_map(static fn (float $v) => round($v, 2), $byCategory);
    }
}
