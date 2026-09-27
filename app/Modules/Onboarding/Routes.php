<?php

/** @var CodeIgniter\Router\RouteCollection $routes */

// URLs live under hr/onboarding (matching Employees/Attendance/Leave/
// Payroll/Performance's existing hr/... convention) even though this is
// its own module namespace/folder — CI4 routing doesn't require the two
// to match, and keeping it a separate module keeps HR's own folder from
// growing indefinitely as more phases are added.
$routes->group('hr/onboarding', ['namespace' => 'App\Modules\Onboarding\Controllers'], static function ($routes) {
    $routes->get('/', 'OnboardingController::index', ['filter' => 'permission:onboarding.view']);
    $routes->get('create', 'OnboardingController::create', ['filter' => 'permission:onboarding.create']);
    $routes->post('/', 'OnboardingController::store', ['filter' => 'permission:onboarding.create']);
    // Phase management (Add/Edit/Reorder/Delete the checklist phases) —
    // reuses these same onboarding.* permission slugs (no new slug
    // added); OnboardingPhaseController itself then narrows that down
    // further to HR/Super Admin only, same layered pattern as
    // OnboardingController's own isOwner() check. Registered before the
    // (:num) routes below purely for readability, same as 'create' —
    // (:num) only matches digits, so 'phases' can never collide with it
    // regardless of order.
    $routes->get('phases', 'OnboardingPhaseController::index', ['filter' => 'permission:onboarding.edit']);
    $routes->get('phases/create', 'OnboardingPhaseController::create', ['filter' => 'permission:onboarding.create']);
    $routes->post('phases', 'OnboardingPhaseController::store', ['filter' => 'permission:onboarding.create']);
    $routes->get('phases/(:num)/edit', 'OnboardingPhaseController::edit/$1', ['filter' => 'permission:onboarding.edit']);
    $routes->post('phases/(:num)', 'OnboardingPhaseController::update/$1', ['filter' => 'permission:onboarding.edit']);
    $routes->post('phases/(:num)/move-up', 'OnboardingPhaseController::moveUp/$1', ['filter' => 'permission:onboarding.edit']);
    $routes->post('phases/(:num)/move-down', 'OnboardingPhaseController::moveDown/$1', ['filter' => 'permission:onboarding.edit']);
    $routes->post('phases/(:num)/delete', 'OnboardingPhaseController::destroy/$1', ['filter' => 'permission:onboarding.delete']);
    $routes->get('(:num)', 'OnboardingController::show/$1', ['filter' => 'permission:onboarding.view']);
    $routes->get('(:num)/offer-letter', 'OnboardingController::offerLetter/$1', ['filter' => 'permission:onboarding.view']);
    $routes->get('(:num)/offer-letter/pdf', 'OnboardingController::offerLetterPdf/$1', ['filter' => 'permission:onboarding.view']);
    $routes->get('(:num)/edit', 'OnboardingController::edit/$1', ['filter' => 'permission:onboarding.edit']);
    $routes->post('(:num)', 'OnboardingController::update/$1', ['filter' => 'permission:onboarding.edit']);
    $routes->post('(:num)/status', 'OnboardingController::updateStatus/$1', ['filter' => 'permission:onboarding.edit']);
    $routes->post('(:num)/link-employee', 'OnboardingController::linkEmployee/$1', ['filter' => 'permission:onboarding.edit']);
    $routes->post('(:num)/delete', 'OnboardingController::destroy/$1', ['filter' => 'permission:onboarding.delete']);
    $routes->post('(:num)/tasks/(:num)', 'OnboardingTaskController::update/$1/$2', ['filter' => 'permission:onboarding.edit']);
});
