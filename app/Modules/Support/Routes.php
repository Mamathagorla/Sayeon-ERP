<?php

/** @var CodeIgniter\Router\RouteCollection $routes */

// "Help" per the user's request — a basic ticket system open to every
// authenticated role (no permission.view gate beyond the global 'auth'
// filter, same reasoning as the Notification module: everyone should
// be able to raise a ticket and see their own). Resolving one (status/
// assignment/notes) is narrowed to support_ticket.edit in the
// controller itself (Company Admin + Super Admin) — see
// SupportController::isResolver().
$routes->group('help', ['namespace' => 'App\Modules\Support\Controllers'], static function ($routes) {
    $routes->get('/', 'SupportController::index');
    $routes->get('create', 'SupportController::create');
    $routes->post('/', 'SupportController::store');
    $routes->get('(:num)', 'SupportController::show/$1');
    $routes->post('(:num)/reply', 'SupportController::reply/$1');
    $routes->post('(:num)/resolve', 'SupportController::resolve/$1', ['filter' => 'permission:support_ticket.edit']);
    $routes->post('(:num)/delete', 'SupportController::delete/$1', ['filter' => 'permission:support_ticket.delete']);
});
