<?php

namespace App\Commands;

use App\Modules\Compliance\Services\ComplianceRecurrenceGenerator;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Production entry point for automatic compliance next-cycle generation
 * — same reasoning and wiring as App\Commands\GenerateRecurringExpenses/
 * GenerateRecurringMeetings.
 *
 *   Linux/production cron:
 *     5 0 * * * cd /path/to/proj2-Multiple-ERP && php spark compliance:generate-recurring >> writable/logs/compliance-recurrence-cron.log 2>&1
 *
 *   Windows Task Scheduler (this dev box):
 *     Action: Start a program
 *     Program/script: C:\path\to\php.exe
 *     Arguments:      spark compliance:generate-recurring
 *     Start in:       C:\...\proj2-Multiple-ERP
 *     Trigger:        Daily
 *
 * Safe to run more than once a day — ComplianceRecurrenceGenerator only
 * ever spawns a next cycle for an item that doesn't already have one
 * (ComplianceItemModel::hasNextCycle()), so a duplicate or overlapping
 * run creates nothing extra.
 */
class GenerateComplianceItems extends BaseCommand
{
    protected $group       = 'Compliance';
    protected $name        = 'compliance:generate-recurring';
    protected $description = 'Generates the next cycle for recurring compliance items nearing/at their due date, across every company.';

    public function run(array $params)
    {
        $result = (new ComplianceRecurrenceGenerator())->runAll();

        CLI::write(
            "Compliance recurrence: {$result['itemsProcessed']} due item(s) processed, "
            . "{$result['itemsGenerated']} next-cycle item(s) generated.",
            'green'
        );
    }
}
