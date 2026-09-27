<?php

/** @var CodeIgniter\Router\RouteCollection $routes */

$routes->group('documents', ['namespace' => 'App\Modules\Document\Controllers'], static function ($routes) {
    $routes->get('/', 'DocumentController::index', ['filter' => 'permission:document.view']);
    $routes->get('create', 'DocumentController::create', ['filter' => 'permission:document.create']);
    $routes->post('/', 'DocumentController::store', ['filter' => 'permission:document.create']);
    $routes->get('(:num)/edit', 'DocumentController::edit/$1', ['filter' => 'permission:document.edit']);
    $routes->post('(:num)', 'DocumentController::update/$1', ['filter' => 'permission:document.edit']);
    $routes->post('(:num)/delete', 'DocumentController::delete/$1', ['filter' => 'permission:document.delete']);
});
