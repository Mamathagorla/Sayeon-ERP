<?php

/** @var CodeIgniter\Router\RouteCollection $routes */

// Vendors — a supporting sub-resource of purchasing, gated by the same
// purchase_order.* permissions rather than a separate permission module
// (same "reuse the parent module's permission" precedent as
// LeaveTypeController reusing leave.edit instead of its own slug).
$routes->group('vendors', ['namespace' => 'App\Modules\Purchase\Controllers'], static function ($routes) {
    $routes->get('/', 'VendorController::index', ['filter' => 'permission:purchase_order.view']);
    $routes->get('create', 'VendorController::create', ['filter' => 'permission:purchase_order.edit']);
    $routes->post('/', 'VendorController::store', ['filter' => 'permission:purchase_order.edit']);
    $routes->get('(:num)/edit', 'VendorController::edit/$1', ['filter' => 'permission:purchase_order.edit']);
    $routes->post('(:num)', 'VendorController::update/$1', ['filter' => 'permission:purchase_order.edit']);
    $routes->post('(:num)/delete', 'VendorController::delete/$1', ['filter' => 'permission:purchase_order.edit']);
});

// Purchase Orders — the approval-workflow view (every request,
// regardless of outcome).
$routes->group('purchase-orders', ['namespace' => 'App\Modules\Purchase\Controllers'], static function ($routes) {
    $routes->get('/', 'PurchaseOrderController::index', ['filter' => 'permission:purchase_order.view']);
    $routes->get('create', 'PurchaseOrderController::create', ['filter' => 'permission:purchase_order.create']);
    $routes->post('/', 'PurchaseOrderController::store', ['filter' => 'permission:purchase_order.create']);
    $routes->get('(:num)', 'PurchaseOrderController::show/$1', ['filter' => 'permission:purchase_order.view']);
    $routes->get('(:num)/edit', 'PurchaseOrderController::edit/$1', ['filter' => 'permission:purchase_order.edit']);
    $routes->post('(:num)', 'PurchaseOrderController::update/$1', ['filter' => 'permission:purchase_order.edit']);
    $routes->post('(:num)/delete', 'PurchaseOrderController::delete/$1', ['filter' => 'permission:purchase_order.delete']);
    $routes->post('(:num)/approve', 'PurchaseOrderController::approve/$1', ['filter' => 'permission:purchase_order.approve']);
    $routes->post('(:num)/reject', 'PurchaseOrderController::reject/$1', ['filter' => 'permission:purchase_order.approve']);
    $routes->post('(:num)/status', 'PurchaseOrderController::updateStatus/$1', ['filter' => 'permission:purchase_order.edit']);
});

// Purchases — the fulfillment view (approved orders only). Same
// controller/table as Purchase Orders above, just a different filtered
// index() — see PurchaseOrderModel::filtered()'s 'approved_only' filter.
$routes->get('purchases', '\App\Modules\Purchase\Controllers\PurchaseOrderController::purchases', ['filter' => 'permission:purchase_order.view']);
