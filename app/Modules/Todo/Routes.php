<?php

/** @var CodeIgniter\Router\RouteCollection $routes */

// Personal data — every logged-in user sees only their own to-dos, so
// these only need the global 'auth' filter, same precedent as the
// Notification module's routes. Open to every role; no permission
// gate and no new permission slug (see TodoController's docblock).
$routes->group('todos', ['namespace' => 'App\Modules\Todo\Controllers'], static function ($routes) {
    $routes->get('/', 'TodoController::index');
    $routes->get('create', 'TodoController::create');
    $routes->post('/', 'TodoController::store');
    $routes->get('(:num)/edit', 'TodoController::edit/$1');
    $routes->post('(:num)', 'TodoController::update/$1');
    $routes->post('(:num)/toggle-complete', 'TodoController::toggleComplete/$1');
    $routes->post('(:num)/toggle-star', 'TodoController::toggleStar/$1');
    $routes->post('(:num)/toggle-important', 'TodoController::toggleImportant/$1');
    $routes->post('(:num)/trash', 'TodoController::trash/$1');
    $routes->post('(:num)/restore', 'TodoController::restore/$1');
    $routes->post('(:num)/delete', 'TodoController::delete/$1');
});
