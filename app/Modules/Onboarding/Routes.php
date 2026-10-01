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
    // A plain employee's self-view entry point — registered before the
    // (:num) routes below for the same reason 'create'/'phases' are:
    // (:num) only matches digits, so 'mine' can never collide with it.
    $routes->get('mine', 'OnboardingController::mine', ['filter' => 'permission:onboarding.view']);
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
    $routes->get('(:num)/joining-letter', 'OnboardingController::joiningLetter/$1', ['filter' => 'permission:onboarding.view']);
    $routes->get('(:num)/joining-letter/pdf', 'OnboardingController::joiningLetterPdf/$1', ['filter' => 'permission:onboarding.view']);
    $routes->get('(:num)/edit', 'OnboardingController::edit/$1', ['filter' => 'permission:onboarding.edit']);
    $routes->post('(:num)', 'OnboardingController::update/$1', ['filter' => 'permission:onboarding.edit']);
    $routes->post('(:num)/status', 'OnboardingController::updateStatus/$1', ['filter' => 'permission:onboarding.edit']);
    $routes->post('(:num)/link-employee', 'OnboardingController::linkEmployee/$1', ['filter' => 'permission:onboarding.edit']);
    $routes->post('(:num)/send-link', 'OnboardingController::sendLink/$1', ['filter' => 'permission:onboarding.edit']);
    // Secure candidate portal — generates/(re)sends the token-based link
    // and reviews what the candidate submitted through it. Distinct from
    // send-link above, which only ever worked once a candidate already
    // had a real ERP login linked (see OnboardingController::sendLink()'s
    // own docblock) — this is for the pre-hire stage that has none.
    $routes->post('(:num)/send-portal-link', 'OnboardingController::sendPortalLink/$1', ['filter' => 'permission:onboarding.edit']);
    $routes->post('(:num)/verification/review', 'OnboardingController::reviewVerification/$1', ['filter' => 'permission:onboarding.edit']);
    $routes->post('(:num)/delete', 'OnboardingController::destroy/$1', ['filter' => 'permission:onboarding.delete']);
    $routes->post('(:num)/tasks/(:num)', 'OnboardingTaskController::update/$1/$2', ['filter' => 'permission:onboarding.edit']);
});

// Candidate-facing portal — deliberately OUTSIDE the hr/onboarding group
// above (no permission:onboarding.* filter, and explicitly excluded from
// the global 'auth' filter in app/Config/Filters.php) since the visitor
// has no ERP login at all. Every action re-validates the token itself
// (OnboardingPortalController::record()) rather than trusting the id in
// the URL, same as MeetingController::join().
$routes->group('onboarding/portal', ['namespace' => 'App\Modules\Onboarding\Controllers'], static function ($routes) {
    $routes->get('(:num)/(:segment)', 'OnboardingPortalController::show/$1/$2');
    $routes->post('(:num)/(:segment)/verify', 'OnboardingPortalController::submitVerification/$1/$2');
    $routes->post('(:num)/(:segment)/offer-response', 'OnboardingPortalController::respondToOffer/$1/$2');
    $routes->get('(:num)/(:segment)/offer-letter', 'OnboardingPortalController::offerLetter/$1/$2');
    $routes->get('(:num)/(:segment)/offer-letter/pdf', 'OnboardingPortalController::offerLetterPdf/$1/$2');
});
