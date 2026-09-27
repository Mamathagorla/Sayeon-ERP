<?php

/** @var CodeIgniter\Router\RouteCollection $routes */

$routes->group('policies', ['namespace' => 'App\Modules\Policy\Controllers'], static function ($routes) {
    $routes->get('/', 'PolicyController::index', ['filter' => 'permission:policy.view']);
    $routes->get('create', 'PolicyController::create', ['filter' => 'permission:policy.create']);
    $routes->post('/', 'PolicyController::store', ['filter' => 'permission:policy.create']);
    $routes->get('(:num)', 'PolicyController::show/$1', ['filter' => 'permission:policy.view']);
    $routes->get('(:num)/edit', 'PolicyController::edit/$1', ['filter' => 'permission:policy.edit']);
    $routes->post('(:num)', 'PolicyController::update/$1', ['filter' => 'permission:policy.edit']);
    $routes->post('(:num)/delete', 'PolicyController::delete/$1', ['filter' => 'permission:policy.delete']);
});
