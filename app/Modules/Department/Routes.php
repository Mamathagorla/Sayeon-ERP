<?php

/** @var CodeIgniter\Router\RouteCollection $routes */

$routes->group('departments', ['namespace' => 'App\Modules\Department\Controllers'], static function ($routes) {
    $routes->get('/', 'DepartmentController::index', ['filter' => 'permission:department.view']);
    $routes->get('create', 'DepartmentController::create', ['filter' => 'permission:department.create']);
    $routes->post('/', 'DepartmentController::store', ['filter' => 'permission:department.create']);
    $routes->get('(:num)/edit', 'DepartmentController::edit/$1', ['filter' => 'permission:department.edit']);
    $routes->post('(:num)', 'DepartmentController::update/$1', ['filter' => 'permission:department.edit']);
    $routes->post('(:num)/delete', 'DepartmentController::delete/$1', ['filter' => 'permission:department.delete']);
});
