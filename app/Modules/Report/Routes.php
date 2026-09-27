<?php

/** @var CodeIgniter\Router\RouteCollection $routes */

$routes->group('reports', ['namespace' => 'App\Modules\Report\Controllers'], static function ($routes) {
    // Each report tab is additionally gated by its own domain permission
    // (not just the blanket report.view) so a role only sees tabs for
    // modules it actually has access to — mirrors how expenses/campaigns
    // already worked, extended to tasks/compliance/companies which
    // previously had no such check.
    $routes->get('tasks', 'ReportController::tasks', ['filter' => 'permission:task.view']);
    $routes->get('tasks/export/(:segment)', 'ReportController::exportTasks/$1', ['filter' => 'permission:task.view']);
    $routes->get('compliance', 'ReportController::compliance', ['filter' => 'permission:compliance.view']);
    $routes->get('compliance/export/(:segment)', 'ReportController::exportCompliance/$1', ['filter' => 'permission:compliance.view']);
    $routes->get('companies', 'ReportController::companies', ['filter' => 'permission:company.view']);
    $routes->get('companies/export/(:segment)', 'ReportController::exportCompanies/$1', ['filter' => 'permission:company.view']);

    // Financial reports are gated by the underlying domain permission
    // (expense.view / invoice.view / bill.view), not just report.view —
    // report.view alone is held by roles (Project Manager, HR Manager,
    // Compliance Officer) that shouldn't necessarily see money figures.
    $routes->get('expenses', 'ReportController::expenses', ['filter' => 'permission:expense.view']);
    $routes->get('expenses/export/(:segment)', 'ReportController::exportExpenses/$1', ['filter' => 'permission:expense.view']);
    $routes->get('receivables', 'ReportController::receivables', ['filter' => 'permission:invoice.view']);
    $routes->get('receivables/export/(:segment)', 'ReportController::exportReceivables/$1', ['filter' => 'permission:invoice.view']);
    $routes->get('payables', 'ReportController::payables', ['filter' => 'permission:bill.view']);
    $routes->get('payables/export/(:segment)', 'ReportController::exportPayables/$1', ['filter' => 'permission:bill.view']);
    // Combines both receivables and payables — requires the fuller
    // (Invoices) capability, same reasoning as the Accounting dashboard.
    $routes->get('financials', 'ReportController::financials', ['filter' => 'permission:invoice.view']);
    $routes->get('financials/export/(:segment)', 'ReportController::exportFinancials/$1', ['filter' => 'permission:invoice.view']);
    // Same "fuller (Invoices) capability" gate as Financial Summary —
    // it also reads bill/expense data, so invoice.view is the coarser
    // of the three, matching that report's own precedent.
    $routes->get('profit-loss', 'ReportController::profitLoss', ['filter' => 'permission:invoice.view']);
    $routes->get('profit-loss/export/(:segment)', 'ReportController::exportProfitLoss/$1', ['filter' => 'permission:invoice.view']);
    // Same "fuller (Invoices) capability" gate as Financial Summary/P&L —
    // it also reads bill/expense data for the Expense side of the table.
    $routes->get('income-vs-expense', 'ReportController::incomeVsExpense', ['filter' => 'permission:invoice.view']);
    $routes->get('income-vs-expense/export/(:segment)', 'ReportController::exportIncomeVsExpense/$1', ['filter' => 'permission:invoice.view']);
    $routes->get('campaigns', 'ReportController::campaigns', ['filter' => 'permission:campaign.view']);
    $routes->get('campaigns/export/(:segment)', 'ReportController::exportCampaigns/$1', ['filter' => 'permission:campaign.view']);
});
