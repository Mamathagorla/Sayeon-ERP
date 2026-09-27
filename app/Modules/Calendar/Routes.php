<?php

/** @var CodeIgniter\Router\RouteCollection $routes */

$routes->group('calendar', ['namespace' => 'App\Modules\Calendar\Controllers'], static function ($routes) {
    $routes->get('/', 'CalendarController::index');
    $routes->get('events', 'CalendarController::events');
});
