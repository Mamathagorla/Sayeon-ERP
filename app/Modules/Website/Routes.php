<?php

/** @var CodeIgniter\Router\RouteCollection $routes */

$routes->group('websites', ['namespace' => 'App\Modules\Website\Controllers'], static function ($routes) {
    $routes->get('/', 'WebsiteController::index', ['filter' => 'permission:website.view']);
    $routes->get('create', 'WebsiteController::create', ['filter' => 'permission:website.create']);
    $routes->post('/', 'WebsiteController::store', ['filter' => 'permission:website.create']);
    $routes->get('(:num)', 'WebsiteController::show/$1', ['filter' => 'permission:website.view']);
    $routes->get('(:num)/edit', 'WebsiteController::edit/$1', ['filter' => 'permission:website.edit']);
    $routes->post('(:num)', 'WebsiteController::update/$1', ['filter' => 'permission:website.edit']);
    $routes->post('(:num)/delete', 'WebsiteController::delete/$1', ['filter' => 'permission:website.delete']);
    $routes->post('(:num)/reveal', 'WebsiteController::reveal/$1', ['filter' => 'permission:website.edit']);
});
