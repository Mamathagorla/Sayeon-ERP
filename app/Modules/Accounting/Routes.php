<?php

/** @var CodeIgniter\Router\RouteCollection $routes */

$routes->group('accounting', ['namespace' => 'App\Modules\Accounting\Controllers'], static function ($routes) {
    // The combined dashboard shows both receivables and payables figures,
    // so it requires the fuller (Invoices) capability — a Bills-only role
    // goes straight to accounting/bills instead (see main.php nav).
    $routes->get('/', 'AccountingController::index', ['filter' => 'permission:invoice.view']);

    $routes->group('invoices', static function ($routes) {
        $routes->get('/', 'InvoiceController::index', ['filter' => 'permission:invoice.view']);
        $routes->get('create', 'InvoiceController::create', ['filter' => 'permission:invoice.create']);
        $routes->post('/', 'InvoiceController::store', ['filter' => 'permission:invoice.create']);
        $routes->get('(:num)', 'InvoiceController::show/$1', ['filter' => 'permission:invoice.view']);
        $routes->get('(:num)/print', 'InvoiceController::print/$1', ['filter' => 'permission:invoice.view']);
        $routes->get('(:num)/pdf', 'InvoiceController::downloadPdf/$1', ['filter' => 'permission:invoice.view']);
        $routes->get('(:num)/edit', 'InvoiceController::edit/$1', ['filter' => 'permission:invoice.edit']);
        $routes->post('(:num)', 'InvoiceController::update/$1', ['filter' => 'permission:invoice.edit']);
        $routes->post('(:num)/delete', 'InvoiceController::delete/$1', ['filter' => 'permission:invoice.delete']);
        $routes->post('(:num)/payments', 'PaymentController::storeForInvoice/$1', ['filter' => 'permission:invoice.edit']);
    });

    $routes->group('bills', static function ($routes) {
        $routes->get('/', 'BillController::index', ['filter' => 'permission:bill.view']);
        $routes->get('create', 'BillController::create', ['filter' => 'permission:bill.create']);
        $routes->post('/', 'BillController::store', ['filter' => 'permission:bill.create']);
        $routes->get('(:num)', 'BillController::show/$1', ['filter' => 'permission:bill.view']);
        $routes->get('(:num)/print', 'BillController::print/$1', ['filter' => 'permission:bill.view']);
        $routes->get('(:num)/pdf', 'BillController::downloadPdf/$1', ['filter' => 'permission:bill.view']);
        $routes->get('(:num)/edit', 'BillController::edit/$1', ['filter' => 'permission:bill.edit']);
        $routes->post('(:num)', 'BillController::update/$1', ['filter' => 'permission:bill.edit']);
        $routes->post('(:num)/delete', 'BillController::delete/$1', ['filter' => 'permission:bill.delete']);
        $routes->post('(:num)/payments', 'PaymentController::storeForBill/$1', ['filter' => 'permission:bill.edit']);
    });

    // Payment deletion doesn't know statically whether it's against an
    // invoice or a bill (single route, looked up at runtime) — no single
    // permission filter fits, so PaymentController::delete() checks
    // invoice.edit/bill.edit itself once it knows which.
    $routes->post('payments/(:num)/delete', 'PaymentController::delete/$1');
});
