<?php

namespace App\Commands;

use App\Modules\Meeting\Services\RecurringMeetingGenerator;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Production entry point for automatic recurring-meeting generation —
 * same reasoning and wiring as App\Commands\GenerateRecurringExpenses
 * (no scheduler package installed, so a plain spark command triggered by
 * the OS's own scheduler is the safest standard mechanism). The daily
 * app/Config/Events.php hook already covers normal usage (the first web
 * request of the day); this command is for a real production cron/Task
 * Scheduler entry, or for testing/catch-up without waiting on a request.
 *
 *   Linux/production cron:
 *     5 0 * * * cd /path/to/proj2-Multiple-ERP && php spark recurring-meetings:generate >> writable/logs/recurring-meetings-cron.log 2>&1
 *
 *   Windows Task Scheduler (this dev box):
 *     Action: Start a program
 *     Program/script: C:\path\to\php.exe
 *     Arguments:      spark recurring-meetings:generate
 *     Start in:       C:\...\proj2-Multiple-ERP
 *     Trigger:        Daily
 *
 * Safe to run more than once a day or on a fresh/never-run install —
 * RecurringMeetingGenerator is fully idempotent (see its own docblock).
 */
class GenerateRecurringMeetings extends BaseCommand
{
    protected $group       = 'Meetings';
    protected $name        = 'recurring-meetings:generate';
    protected $description = 'Generates due meetings from active recurring meeting templates, across every company.';

    public function run(array $params)
    {
        $result = (new RecurringMeetingGenerator())->runAll();

        CLI::write(
            "Recurring meetings: {$result['templatesProcessed']} template(s) processed, "
            . "{$result['meetingsGenerated']} meeting(s) generated.",
            'green'
        );
    }
}
