<?php

/** @var CodeIgniter\Router\RouteCollection $routes */

$routes->group('companies', ['namespace' => 'App\Modules\Company\Controllers'], static function ($routes) {
    $routes->get('/', 'CompanyController::index', ['filter' => 'permission:company.view']);
    $routes->get('create', 'CompanyController::create', ['filter' => 'permission:company.create']);
    $routes->post('/', 'CompanyController::store', ['filter' => 'permission:company.create']);
    $routes->get('(:num)', 'CompanyController::show/$1', ['filter' => 'permission:company.view']);
    $routes->get('(:num)/edit', 'CompanyController::edit/$1', ['filter' => 'permission:company.edit']);
    $routes->post('(:num)', 'CompanyController::update/$1', ['filter' => 'permission:company.edit']);
    $routes->post('(:num)/delete', 'CompanyController::delete/$1', ['filter' => 'permission:company.delete']);

    // Directors sub-resource, nested under a company
    $routes->post('(:num)/directors', 'DirectorController::store/$1', ['filter' => 'permission:company.edit']);
    $routes->get('(:num)/directors/(:num)/edit', 'DirectorController::edit/$1/$2', ['filter' => 'permission:company.edit']);
    $routes->post('(:num)/directors/(:num)', 'DirectorController::update/$1/$2', ['filter' => 'permission:company.edit']);
    $routes->post('(:num)/directors/(:num)/delete', 'DirectorController::delete/$1/$2', ['filter' => 'permission:company.edit']);

    // Bank accounts sub-resource, nested under a company — gated by its own
    // bank_account.* permissions (more sensitive than general company.edit).
    $routes->post('(:num)/bank-accounts', 'BankAccountController::store/$1', ['filter' => 'permission:bank_account.create']);
    $routes->get('(:num)/bank-accounts/(:num)/edit', 'BankAccountController::edit/$1/$2', ['filter' => 'permission:bank_account.edit']);
    $routes->post('(:num)/bank-accounts/(:num)', 'BankAccountController::update/$1/$2', ['filter' => 'permission:bank_account.edit']);
    $routes->post('(:num)/bank-accounts/(:num)/delete', 'BankAccountController::delete/$1/$2', ['filter' => 'permission:bank_account.delete']);
});
