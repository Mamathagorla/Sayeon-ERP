<?php

/** @var CodeIgniter\Router\RouteCollection $routes */

// hr/offboarding, matching every other HR sub-feature's hr/... URL
// convention even though this is its own module namespace/folder.
$routes->group('hr/offboarding', ['namespace' => 'App\Modules\Offboarding\Controllers'], static function ($routes) {
    $routes->get('/', 'OffboardingController::index', ['filter' => 'permission:offboarding.view']);
    $routes->get('create', 'OffboardingController::create', ['filter' => 'permission:offboarding.create']);
    $routes->post('/', 'OffboardingController::store', ['filter' => 'permission:offboarding.create']);
    // Phase management — same reasoning as Onboarding's own phase
    // routes (see that Routes.php's comment): reuses the existing
    // offboarding.* permission slugs, narrowed to HR/Super Admin only
    // inside OffboardingPhaseController itself.
    $routes->get('phases', 'OffboardingPhaseController::index', ['filter' => 'permission:offboarding.edit']);
    $routes->get('phases/create', 'OffboardingPhaseController::create', ['filter' => 'permission:offboarding.create']);
    $routes->post('phases', 'OffboardingPhaseController::store', ['filter' => 'permission:offboarding.create']);
    $routes->get('phases/(:num)/edit', 'OffboardingPhaseController::edit/$1', ['filter' => 'permission:offboarding.edit']);
    $routes->post('phases/(:num)', 'OffboardingPhaseController::update/$1', ['filter' => 'permission:offboarding.edit']);
    $routes->post('phases/(:num)/move-up', 'OffboardingPhaseController::moveUp/$1', ['filter' => 'permission:offboarding.edit']);
    $routes->post('phases/(:num)/move-down', 'OffboardingPhaseController::moveDown/$1', ['filter' => 'permission:offboarding.edit']);
    $routes->post('phases/(:num)/delete', 'OffboardingPhaseController::destroy/$1', ['filter' => 'permission:offboarding.delete']);
    $routes->get('(:num)', 'OffboardingController::show/$1', ['filter' => 'permission:offboarding.view']);
    $routes->get('(:num)/relieving-letter', 'OffboardingController::relievingLetter/$1', ['filter' => 'permission:offboarding.view']);
    $routes->get('(:num)/relieving-letter/pdf', 'OffboardingController::relievingLetterPdf/$1', ['filter' => 'permission:offboarding.view']);
    $routes->get('(:num)/edit', 'OffboardingController::edit/$1', ['filter' => 'permission:offboarding.edit']);
    $routes->post('(:num)', 'OffboardingController::update/$1', ['filter' => 'permission:offboarding.edit']);
    $routes->post('(:num)/status', 'OffboardingController::updateStatus/$1', ['filter' => 'permission:offboarding.edit']);
    $routes->post('(:num)/delete', 'OffboardingController::destroy/$1', ['filter' => 'permission:offboarding.delete']);
    $routes->post('(:num)/tasks/(:num)', 'OffboardingTaskController::update/$1/$2', ['filter' => 'permission:offboarding.edit']);
});
