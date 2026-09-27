<?php

namespace App\Commands;

use App\Modules\Expense\Services\RecurringExpenseGenerator;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Production entry point for automatic recurring-expense generation.
 * This app has no scheduler package installed (no app/Config/Tasks.php,
 * no `tasks:run` spark command — checked before adding this), so a
 * plain spark CLI command triggered by the OS's own scheduler is the
 * safest standard mechanism: it runs the full app bootstrap (same DB
 * config, same models) with no HTTP exposure and no new dependency.
 *
 * Wire it up with ONE of:
 *
 *   Linux/production cron (daily is enough — this only needs date
 *   granularity, not per-minute):
 *     5 0 * * * cd /path/to/proj2-Multiple-ERP && php spark recurring-expenses:generate >> writable/logs/recurring-expenses-cron.log 2>&1
 *
 *   Windows Task Scheduler (this dev box):
 *     Action: Start a program
 *     Program/script: C:\path\to\php.exe
 *     Arguments:      spark recurring-expenses:generate
 *     Start in:       C:\...\proj2-Multiple-ERP
 *     Trigger:        Daily
 *
 * Safe to run more than once a day, more than once total, or on a
 * fresh/never-run install — RecurringExpenseGenerator is fully
 * idempotent (see its own docblock), so a duplicate or overlapping
 * run generates nothing extra.
 */
class GenerateRecurringExpenses extends BaseCommand
{
    protected $group       = 'Expenses';
    protected $name        = 'recurring-expenses:generate';
    protected $description = 'Generates due expense records from active recurring expense templates, across every company.';

    public function run(array $params)
    {
        $result = (new RecurringExpenseGenerator())->runAll();

        CLI::write(
            "Recurring expenses: {$result['templatesProcessed']} template(s) processed, "
            . "{$result['expensesGenerated']} expense(s) generated.",
            'green'
        );
    }
}
