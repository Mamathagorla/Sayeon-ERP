<?php

/** @var CodeIgniter\Router\RouteCollection $routes */

$routes->group('expenses', ['namespace' => 'App\Modules\Expense\Controllers'], static function ($routes) {
    $routes->get('/', 'ExpenseController::index', ['filter' => 'permission:expense.view']);
    $routes->get('create', 'ExpenseController::create', ['filter' => 'permission:expense.create']);
    $routes->post('/', 'ExpenseController::store', ['filter' => 'permission:expense.create']);
    $routes->get('(:num)/edit', 'ExpenseController::edit/$1', ['filter' => 'permission:expense.edit']);
    $routes->post('(:num)', 'ExpenseController::update/$1', ['filter' => 'permission:expense.edit']);
    $routes->post('(:num)/delete', 'ExpenseController::delete/$1', ['filter' => 'permission:expense.delete']);
});

// Recurring expense templates — same expense.* permissions as the
// expenses group above, no new permission slug (this is just another
// way of creating expenses, not a separate resource).
$routes->group('recurring-expenses', ['namespace' => 'App\Modules\Expense\Controllers'], static function ($routes) {
    $routes->get('/', 'RecurringExpenseController::index', ['filter' => 'permission:expense.view']);
    $routes->get('create', 'RecurringExpenseController::create', ['filter' => 'permission:expense.create']);
    $routes->post('/', 'RecurringExpenseController::store', ['filter' => 'permission:expense.create']);
    $routes->post('generate-now', 'RecurringExpenseController::generateNow', ['filter' => 'permission:expense.create']);
    $routes->get('(:num)', 'RecurringExpenseController::show/$1', ['filter' => 'permission:expense.view']);
    $routes->get('(:num)/edit', 'RecurringExpenseController::edit/$1', ['filter' => 'permission:expense.edit']);
    $routes->post('(:num)', 'RecurringExpenseController::update/$1', ['filter' => 'permission:expense.edit']);
    $routes->post('(:num)/pause', 'RecurringExpenseController::pause/$1', ['filter' => 'permission:expense.edit']);
    $routes->post('(:num)/resume', 'RecurringExpenseController::resume/$1', ['filter' => 'permission:expense.edit']);
    $routes->post('(:num)/cancel', 'RecurringExpenseController::cancel/$1', ['filter' => 'permission:expense.delete']);
});

$routes->group('expense-claims', ['namespace' => 'App\Modules\Expense\Controllers'], static function ($routes) {
    $routes->get('/', 'ExpenseClaimController::index', ['filter' => 'permission:expense_claim.view']);
    $routes->get('create', 'ExpenseClaimController::create', ['filter' => 'permission:expense_claim.create']);
    $routes->post('/', 'ExpenseClaimController::store', ['filter' => 'permission:expense_claim.create']);
    $routes->post('(:num)/cancel', 'ExpenseClaimController::cancel/$1', ['filter' => 'permission:expense_claim.create']);
    $routes->post('(:num)/approve', 'ExpenseClaimController::approve/$1', ['filter' => 'permission:expense_claim.approve']);
    $routes->post('(:num)/reject', 'ExpenseClaimController::reject/$1', ['filter' => 'permission:expense_claim.approve']);
    $routes->post('(:num)/reimburse', 'ExpenseClaimController::reimburse/$1', ['filter' => 'permission:expense_claim.reimburse']);
});
