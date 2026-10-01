<?php

/** @var CodeIgniter\Router\RouteCollection $routes */

$routes->group('compliance', ['namespace' => 'App\Modules\Compliance\Controllers'], static function ($routes) {
    $routes->get('/', 'ComplianceController::index', ['filter' => 'permission:compliance.view']);
    $routes->get('create', 'ComplianceController::create', ['filter' => 'permission:compliance.create']);
    $routes->post('/', 'ComplianceController::store', ['filter' => 'permission:compliance.create']);

    // Company-specific compliance type applicability — "which of the
    // shared compliance types actually apply to this company". No new
    // permission slug, same compliance.edit reuse as generate-recurring
    // above. Defined before the (:num) routes purely for readability —
    // (:num) only ever matches digits, so "types" can never collide
    // with it regardless of order.
    $routes->get('types/settings', 'ComplianceController::typeSettings', ['filter' => 'permission:compliance.edit']);
    $routes->post('types/settings', 'ComplianceController::saveTypeSettings', ['filter' => 'permission:compliance.edit']);
    $routes->post('types', 'ComplianceController::createType', ['filter' => 'permission:compliance.create']);

    $routes->get('(:num)', 'ComplianceController::show/$1', ['filter' => 'permission:compliance.view']);
    $routes->get('(:num)/edit', 'ComplianceController::edit/$1', ['filter' => 'permission:compliance.edit']);
    $routes->post('(:num)', 'ComplianceController::update/$1', ['filter' => 'permission:compliance.edit']);
    $routes->post('(:num)/delete', 'ComplianceController::delete/$1', ['filter' => 'permission:compliance.delete']);
    $routes->post('(:num)/mark-filed', 'ComplianceController::markFiled/$1', ['filter' => 'permission:compliance.edit']);
    // No new permission slug — reuses compliance.edit, same "generate is
    // just another way of creating the next cycle" reasoning as
    // recurring-expenses/recurring-meetings reusing their parent module's
    // permissions instead of minting their own.
    $routes->post('generate-recurring', 'ComplianceController::generateRecurring', ['filter' => 'permission:compliance.edit']);

    $routes->post('(:num)/attachments', 'ComplianceController::uploadAttachment/$1', ['filter' => 'permission:compliance.edit']);
    $routes->post('(:num)/attachments/(:num)/delete', 'ComplianceController::deleteAttachment/$1/$2', ['filter' => 'permission:compliance.edit']);
});
