<?php

/** @var CodeIgniter\Router\RouteCollection $routes */

// Customers — its own permission slug set (customer.*, seeded in
// PermissionSeeder.php) rather than reusing Invoice's, since Customer is
// referenced from more than one place (Invoice today, plausibly other
// modules later) and deserves independent access control from day one —
// unlike Vendor, which reuses purchase_order.* because it's purely a
// purchasing sub-resource.
$routes->group('customers', ['namespace' => 'App\Modules\Customer\Controllers'], static function ($routes) {
    $routes->get('/', 'CustomerController::index', ['filter' => 'permission:customer.view']);
    $routes->get('create', 'CustomerController::create', ['filter' => 'permission:customer.create']);
    $routes->post('/', 'CustomerController::store', ['filter' => 'permission:customer.create']);
    $routes->get('(:num)', 'CustomerController::show/$1', ['filter' => 'permission:customer.view']);
    $routes->get('(:num)/edit', 'CustomerController::edit/$1', ['filter' => 'permission:customer.edit']);
    $routes->post('(:num)', 'CustomerController::update/$1', ['filter' => 'permission:customer.edit']);
    $routes->post('(:num)/delete', 'CustomerController::delete/$1', ['filter' => 'permission:customer.delete']);
});
