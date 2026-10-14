<?php

/** @var CodeIgniter\Router\RouteCollection $routes */

$routes->group('meetings', ['namespace' => 'App\Modules\Meeting\Controllers'], static function ($routes) {
    $routes->get('/', 'MeetingController::index', ['filter' => 'permission:meeting.view']);
    $routes->get('create', 'MeetingController::create', ['filter' => 'permission:meeting.create']);
    $routes->post('/', 'MeetingController::store', ['filter' => 'permission:meeting.create']);

    // Per-organizer Google account connection for real Google Meet
    // creation (see GoogleOAuthController) — behind the normal 'auth'
    // filter like everything else here (not a public/token route, since
    // it links a Google account to the CURRENT logged-in ERP user).
    // Registered before the (:num) routes purely for readability —
    // (:num) only ever matches digits, so 'google' can never collide
    // with it regardless of order.
    $routes->get('google/connect', 'GoogleOAuthController::connect', ['filter' => 'permission:meeting.create']);
    $routes->get('google/callback', 'GoogleOAuthController::callback', ['filter' => 'permission:meeting.create']);
    $routes->post('google/disconnect', 'GoogleOAuthController::disconnect', ['filter' => 'permission:meeting.create']);
    $routes->get('(:num)', 'MeetingController::show/$1', ['filter' => 'permission:meeting.view']);
    // The auto-generated join link — permission:meeting.view is only the
    // first gate (must be logged in with basic meeting access at all);
    // MeetingController::join() itself checks the token and the real
    // participant-or-staff authorization before granting access.
    $routes->get('(:num)/join/(:segment)', 'MeetingController::join/$1/$2', ['filter' => 'permission:meeting.view']);
    $routes->get('(:num)/edit', 'MeetingController::edit/$1', ['filter' => 'permission:meeting.edit']);
    $routes->post('(:num)', 'MeetingController::update/$1', ['filter' => 'permission:meeting.edit']);
    $routes->post('(:num)/delete', 'MeetingController::delete/$1', ['filter' => 'permission:meeting.delete']);
    $routes->post('(:num)/mom', 'MeetingController::updateMom/$1', ['filter' => 'permission:meeting.edit']);

    // Participants
    $routes->post('(:num)/participants', 'ParticipantController::store/$1', ['filter' => 'permission:meeting.edit']);
    $routes->post('(:num)/participants/(:num)/delete', 'ParticipantController::delete/$1/$2', ['filter' => 'permission:meeting.edit']);

    // Action items (+ follow-up task conversion)
    $routes->post('(:num)/action-items', 'ActionItemController::store/$1', ['filter' => 'permission:meeting.edit']);
    $routes->post('(:num)/action-items/(:num)/status', 'ActionItemController::updateStatus/$1/$2', ['filter' => 'permission:meeting.edit']);
    $routes->post('(:num)/action-items/(:num)/convert', 'ActionItemController::convertToTask/$1/$2', ['filter' => 'permission:task.create']);
    $routes->post('(:num)/action-items/(:num)/delete', 'ActionItemController::delete/$1/$2', ['filter' => 'permission:meeting.edit']);
});

// Recurring meetings — "automatic meeting creation based on time", using
// the same meeting.* permissions as regular meetings (no new slug — same
// reasoning as recurring-expenses reusing expense.*: this is just
// another way of creating meetings, not a separate resource).
$routes->group('recurring-meetings', ['namespace' => 'App\Modules\Meeting\Controllers'], static function ($routes) {
    $routes->get('/', 'RecurringMeetingController::index', ['filter' => 'permission:meeting.view']);
    $routes->get('create', 'RecurringMeetingController::create', ['filter' => 'permission:meeting.create']);
    $routes->post('/', 'RecurringMeetingController::store', ['filter' => 'permission:meeting.create']);
    $routes->post('generate-now', 'RecurringMeetingController::generateNow', ['filter' => 'permission:meeting.create']);
    $routes->get('(:num)/edit', 'RecurringMeetingController::edit/$1', ['filter' => 'permission:meeting.edit']);
    $routes->post('(:num)', 'RecurringMeetingController::update/$1', ['filter' => 'permission:meeting.edit']);
    $routes->post('(:num)/pause', 'RecurringMeetingController::pause/$1', ['filter' => 'permission:meeting.edit']);
    $routes->post('(:num)/resume', 'RecurringMeetingController::resume/$1', ['filter' => 'permission:meeting.edit']);
    $routes->post('(:num)/cancel', 'RecurringMeetingController::cancel/$1', ['filter' => 'permission:meeting.delete']);
});
