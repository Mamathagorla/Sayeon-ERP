<?php

/** @var CodeIgniter\Router\RouteCollection $routes */

// Global topbar search — every logged-in user hits this (no single
// permission gate at the route level); each entity type is filtered
// inside the controller by that entity's own can('*.view') check, same
// as every list page already does.
$routes->group('search', ['namespace' => 'App\Modules\Search\Controllers'], static function ($routes) {
    $routes->get('live', 'SearchController::live');
});
