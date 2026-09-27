<?php

/** @var CodeIgniter\Router\RouteCollection $routes */

$routes->group('meetings', ['namespace' => 'App\Modules\Meeting\Controllers'], static function ($routes) {
    $routes->get('/', 'MeetingController::index', ['filter' => 'permission:meeting.view']);
    $routes->get('create', 'MeetingController::create', ['filter' => 'permission:meeting.create']);
    $routes->post('/', 'MeetingController::store', ['filter' => 'permission:meeting.create']);
    $routes->get('(:num)', 'MeetingController::show/$1', ['filter' => 'permission:meeting.view']);
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
