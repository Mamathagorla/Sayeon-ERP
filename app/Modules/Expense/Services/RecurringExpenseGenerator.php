<?php

namespace App\Modules\Expense\Services;

use App\Modules\Expense\Models\ExpenseModel;
use App\Modules\Expense\Models\RecurringExpenseModel;

/**
 * The single place that turns a recurring_expenses template into real
 * `expenses` rows. Both the CLI command (App\Commands\GenerateRecurringExpenses,
 * for a production cron) and RecurringExpenseController::generateNow()
 * (a manual, permission-gated trigger for on-demand catch-up/testing)
 * call runAll()/runForCompany() — there is exactly one code path that
 * writes generated expenses, so the duplicate-prevention and stop-
 * condition logic only has to be correct in one place.
 */
class RecurringExpenseGenerator
{
    private RecurringExpenseModel $recurringModel;
    private ExpenseModel $expenseModel;

    // Defensive cap on how many occurrences one run() call will ever
    // generate for a single template, independent of its own
    // occurrences_total — guards against an unforeseen logic bug ever
    // turning into an infinite/runaway loop. RecurringExpenseModel's own
    // MAX_OCCURRENCES (240) already keeps a template's total this low
    // in practice, so this should never actually bind.
    private const MAX_PER_RUN = 500;

    public function __construct(?RecurringExpenseModel $recurringModel = null, ?ExpenseModel $expenseModel = null)
    {
        $this->recurringModel = $recurringModel ?? new RecurringExpenseModel();
        $this->expenseModel   = $expenseModel ?? new ExpenseModel();
    }

    /**
     * Automatic trigger: the first web request of each day runs the
     * generator for every company, so due payments appear without anyone
     * clicking "Generate Now" or setting up a cron job. The day is marked
     * as done before running, so concurrent requests can't double-run it
     * and a failure isn't retried on every page load (the generator is
     * idempotent and catches up on the next day's run).
     */
    public function runOncePerDay(): void
    {
        $cache = cache();
        $today = date('Y-m-d');

        if ($cache->get('recurring_expenses_last_run') === $today) {
            return;
        }
        $cache->save('recurring_expenses_last_run', $today, 172800);

        try {
            $result = $this->runAll();
            if ($result['expensesGenerated'] > 0) {
                log_message('info', "Recurring expenses (auto): {$result['expensesGenerated']} expense(s) generated from {$result['templatesProcessed']} template(s).");
            }
        } catch (\Throwable $e) {
            log_message('error', 'Recurring expenses auto-run failed: ' . $e->getMessage());
        }
    }

    /**
     * Processes every active, due template across every company — what
     * the cron/CLI command calls.
     *
     * @return array{templatesProcessed:int, expensesGenerated:int}
     */
    public function runAll(): array
    {
        return $this->run(null);
    }

    /**
     * Same as runAll(), narrowed to one company — what a scoped "Generate
     * Now" click in the web UI calls, so a Company Admin can only ever
     * trigger generation for their own company's templates.
     *
     * @return array{templatesProcessed:int, expensesGenerated:int}
     */
    public function runForCompany(int $companyId): array
    {
        return $this->run($companyId);
    }

    private function run(?int $companyId): array
    {
        $templates         = $this->recurringModel->dueForGeneration($companyId);
        $templatesProcessed = 0;
        $expensesGenerated  = 0;

        foreach ($templates as $template) {
            $generated = $this->runOne($template);
            if ($generated > 0) {
                $templatesProcessed++;
                $expensesGenerated += $generated;
            }
        }

        return ['templatesProcessed' => $templatesProcessed, 'expensesGenerated' => $expensesGenerated];
    }

    /**
     * Catches up a single template — generates every occurrence whose
     * scheduled date has already arrived (not just the next one), so a
     * template that missed several cycles (cron was down, or its start
     * date is in the past) is brought fully up to date in one call.
     * Idempotent: re-running does nothing once a template is caught up,
     * completed, or paused/cancelled.
     */
    public function runOne(array $template): int
    {
        $generatedCount = 0;

        for ($i = 0; $i < self::MAX_PER_RUN; $i++) {
            if ($template['status'] !== 'active') {
                break;
            }
            if ($template['next_generation_date'] === null) {
                break;
            }
            if ($template['next_generation_date'] > date('Y-m-d')) {
                break;
            }

            $occurrenceNumber = (int) $template['occurrences_generated'] + 1;

            // Belt-and-suspenders alongside the DB unique index
            // (expenses.uniq_recurring_occurrence) — if this exact
            // occurrence somehow already exists, stop instead of
            // colliding with the constraint or double-counting.
            $alreadyExists = $this->expenseModel
                ->where('recurring_expense_id', $template['id'])
                ->where('occurrence_number', $occurrenceNumber)
                ->countAllResults() > 0;

            if ($alreadyExists) {
                break;
            }

            $this->expenseModel->insert([
                'company_id'            => $template['company_id'],
                'recurring_expense_id'  => $template['id'],
                'occurrence_number'     => $occurrenceNumber,
                'vendor'                => $template['title'],
                'category'              => $template['category'],
                'billing_cycle'         => $template['frequency'],
                'amount'                => $template['amount'],
                'renewal_date'          => $template['next_generation_date'],
                'auto_renewal'          => 0,
                'payment_method'        => null,
                'notes'                 => $template['description'] !== null && $template['description'] !== ''
                    ? mb_substr($template['description'], 0, 255)
                    : null,
                'status'                => 'active',
                'created_by'            => $template['created_by'],
            ]);

            $generatedCount++;

            $template['occurrences_generated'] = $occurrenceNumber;
            $template['next_generation_date']  = $this->recurringModel->advanceDate($template['next_generation_date'], $template['frequency']);

            $reachedOccurrenceLimit = $template['end_type'] === 'occurrences'
                && $template['occurrences_total'] !== null
                && $occurrenceNumber >= (int) $template['occurrences_total'];

            $reachedEndDate = $template['end_type'] === 'end_date'
                && $template['end_date'] !== null
                && $template['next_generation_date'] > $template['end_date'];

            if ($reachedOccurrenceLimit || $reachedEndDate) {
                $template['status']               = 'completed';
                $template['next_generation_date'] = null;
            }

            $this->recurringModel->update($template['id'], [
                'occurrences_generated' => $template['occurrences_generated'],
                'next_generation_date'  => $template['next_generation_date'],
                'status'                => $template['status'],
            ]);
        }

        return $generatedCount;
    }
}
