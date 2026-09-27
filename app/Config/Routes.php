<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->setDefaultNamespace('App\Controllers');
$routes->setDefaultController('Home');
$routes->setDefaultMethod('index');
$routes->setTranslateURIDashes(false);
$routes->set404Override();
$routes->setAutoRoute(false); // explicit routes only — no implicit controller routing

// Landing → dashboard (AuthFilter will redirect to /auth/login if not signed in)
$routes->get('/', '\App\Modules\Dashboard\Controllers\DashboardController::index');

// Gated file download — see App\Controllers\FileController
$routes->get('files/download', '\App\Controllers\FileController::download');

// Super Admin's active-company switcher (topbar) — role-checked inside
// the controller itself, not a permission:* filter, since every logged-in
// role hits this URL (Company Admin gets a friendly rejection, everyone
// else too) rather than a 403.
$routes->post('company-switch', '\App\Modules\Company\Controllers\CompanyController::switchActive');

/*
 * Each HMVC module owns its own routes and is included here.
 * Keeps this file from becoming a 500-line dumping ground as
 * modules are added phase by phase.
 */
require APPPATH . 'Modules/Auth/Routes.php';
require APPPATH . 'Modules/Company/Routes.php';
require APPPATH . 'Modules/Department/Routes.php';
require APPPATH . 'Modules/Task/Routes.php';
require APPPATH . 'Modules/Dashboard/Routes.php';
require APPPATH . 'Modules/Meeting/Routes.php';
require APPPATH . 'Modules/Compliance/Routes.php';
require APPPATH . 'Modules/Calendar/Routes.php';
require APPPATH . 'Modules/Notification/Routes.php';
require APPPATH . 'Modules/Search/Routes.php';
require APPPATH . 'Modules/Report/Routes.php';
require APPPATH . 'Modules/HR/Routes.php';
require APPPATH . 'Modules/Onboarding/Routes.php';
require APPPATH . 'Modules/Offboarding/Routes.php';
require APPPATH . 'Modules/Policy/Routes.php';
require APPPATH . 'Modules/Purchase/Routes.php';
require APPPATH . 'Modules/Support/Routes.php';
require APPPATH . 'Modules/Expense/Routes.php';
require APPPATH . 'Modules/Accounting/Routes.php';
require APPPATH . 'Modules/Website/Routes.php';
require APPPATH . 'Modules/Document/Routes.php';
require APPPATH . 'Modules/Marketing/Routes.php';
require APPPATH . 'Modules/Todo/Routes.php';
