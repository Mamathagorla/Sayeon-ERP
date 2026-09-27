<?php

/** @var CodeIgniter\Router\RouteCollection $routes */

$routes->group('tasks', ['namespace' => 'App\Modules\Task\Controllers'], static function ($routes) {
    $routes->get('/', 'TaskController::index', ['filter' => 'permission:task.view']);
    $routes->get('create', 'TaskController::create', ['filter' => 'permission:task.create']);
    $routes->post('/', 'TaskController::store', ['filter' => 'permission:task.create']);
    $routes->get('(:num)', 'TaskController::show/$1', ['filter' => 'permission:task.view']);
    $routes->get('(:num)/edit', 'TaskController::edit/$1', ['filter' => 'permission:task.edit']);
    $routes->post('(:num)', 'TaskController::update/$1', ['filter' => 'permission:task.edit']);
    $routes->post('(:num)/delete', 'TaskController::delete/$1', ['filter' => 'permission:task.delete']);
    $routes->post('(:num)/status', 'TaskController::updateStatus/$1', ['filter' => 'permission:task.edit']);

    $routes->post('(:num)/comments', 'TaskController::addComment/$1', ['filter' => 'permission:task.edit']);

    $routes->post('(:num)/checklist', 'TaskController::addChecklistItem/$1', ['filter' => 'permission:task.edit']);
    $routes->post('(:num)/checklist/(:num)/toggle', 'TaskController::toggleChecklistItem/$1/$2', ['filter' => 'permission:task.edit']);
    $routes->post('(:num)/checklist/(:num)/delete', 'TaskController::deleteChecklistItem/$1/$2', ['filter' => 'permission:task.edit']);

    $routes->post('(:num)/attachments', 'TaskController::uploadAttachment/$1', ['filter' => 'permission:task.edit']);
    $routes->post('(:num)/attachments/(:num)/delete', 'TaskController::deleteAttachment/$1/$2', ['filter' => 'permission:task.edit']);
});

$routes->group('projects', ['namespace' => 'App\Modules\Task\Controllers'], static function ($routes) {
    $routes->get('/', 'ProjectController::index', ['filter' => 'permission:project.view']);
    $routes->get('create', 'ProjectController::create', ['filter' => 'permission:project.create']);
    $routes->post('/', 'ProjectController::store', ['filter' => 'permission:project.create']);
    $routes->get('(:num)', 'ProjectController::show/$1', ['filter' => 'permission:project.view']);
    $routes->get('(:num)/edit', 'ProjectController::edit/$1', ['filter' => 'permission:project.edit']);
    $routes->post('(:num)', 'ProjectController::update/$1', ['filter' => 'permission:project.edit']);
    $routes->post('(:num)/delete', 'ProjectController::delete/$1', ['filter' => 'permission:project.delete']);
});
