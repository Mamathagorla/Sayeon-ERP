<?php

namespace Config;

use CodeIgniter\Config\AutoloadConfig;

/**
 * Hand-rolled HMVC: every module is registered here as its own PSR-4
 * namespace so Controllers/Models/Views/Filters/Migrations stay self
 * contained under app/Modules/<Name>/ instead of the flat app/ tree.
 *
 * When adding a new module in a later phase: add its namespace here
 * AND to composer.json's autoload.psr-4 block, then run
 * `composer dump-autoload`.
 */
class Autoload extends AutoloadConfig
{
    public $psr4 = [
        APP_NAMESPACE => APPPATH,
        'Config'      => APPPATH . 'Config',

        'App\\Modules\\Auth'       => APPPATH . 'Modules/Auth',
        'App\\Modules\\Company'    => APPPATH . 'Modules/Company',
        'App\\Modules\\Department' => APPPATH . 'Modules/Department',
        'App\\Modules\\Task'       => APPPATH . 'Modules/Task',
        'App\\Modules\\Dashboard'  => APPPATH . 'Modules/Dashboard',
        'App\\Modules\\Meeting'    => APPPATH . 'Modules/Meeting',
        'App\\Modules\\Compliance' => APPPATH . 'Modules/Compliance',
        'App\\Modules\\Calendar'   => APPPATH . 'Modules/Calendar',
        'App\\Modules\\Notification' => APPPATH . 'Modules/Notification',
        'App\\Modules\\Report'     => APPPATH . 'Modules/Report',
        'App\\Modules\\HR'         => APPPATH . 'Modules/HR',
        'App\\Modules\\Expense'    => APPPATH . 'Modules/Expense',
        'App\\Modules\\Accounting' => APPPATH . 'Modules/Accounting',
        'App\\Modules\\Website'    => APPPATH . 'Modules/Website',
        'App\\Modules\\Document'   => APPPATH . 'Modules/Document',
        'App\\Modules\\Marketing'  => APPPATH . 'Modules/Marketing',
        'App\\Modules\\Onboarding' => APPPATH . 'Modules/Onboarding',
        'App\\Modules\\Offboarding' => APPPATH . 'Modules/Offboarding',
        'App\\Modules\\Policy'      => APPPATH . 'Modules/Policy',
        'App\\Modules\\Purchase'    => APPPATH . 'Modules/Purchase',
        'App\\Modules\\Support'     => APPPATH . 'Modules/Support',
        'App\\Modules\\Todo'        => APPPATH . 'Modules/Todo',
    ];

    public $classmap = [];

    public $files = [];

    public array $helpers = ['url', 'form', 'text', 'array', 'permission', 'payroll'];
}
