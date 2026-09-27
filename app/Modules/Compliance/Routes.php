<?php

/** @var CodeIgniter\Router\RouteCollection $routes */

$routes->group('compliance', ['namespace' => 'App\Modules\Compliance\Controllers'], static function ($routes) {
    $routes->get('/', 'ComplianceController::index', ['filter' => 'permission:compliance.view']);
    $routes->get('create', 'ComplianceController::create', ['filter' => 'permission:compliance.create']);
    $routes->post('/', 'ComplianceController::store', ['filter' => 'permission:compliance.create']);
    $routes->get('(:num)', 'ComplianceController::show/$1', ['filter' => 'permission:compliance.view']);
    $routes->get('(:num)/edit', 'ComplianceController::edit/$1', ['filter' => 'permission:compliance.edit']);
    $routes->post('(:num)', 'ComplianceController::update/$1', ['filter' => 'permission:compliance.edit']);
    $routes->post('(:num)/delete', 'ComplianceController::delete/$1', ['filter' => 'permission:compliance.delete']);
    $routes->post('(:num)/mark-filed', 'ComplianceController::markFiled/$1', ['filter' => 'permission:compliance.edit']);

    $routes->post('(:num)/attachments', 'ComplianceController::uploadAttachment/$1', ['filter' => 'permission:compliance.edit']);
    $routes->post('(:num)/attachments/(:num)/delete', 'ComplianceController::deleteAttachment/$1/$2', ['filter' => 'permission:compliance.edit']);
});
