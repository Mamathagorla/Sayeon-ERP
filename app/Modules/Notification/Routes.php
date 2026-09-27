<?php

/** @var CodeIgniter\Router\RouteCollection $routes */

// Personal data — every logged-in user sees only their own notifications,
// so these only need the global 'auth' filter, no permission gate.
$routes->group('notifications', ['namespace' => 'App\Modules\Notification\Controllers'], static function ($routes) {
    $routes->get('/', 'NotificationController::index');
    $routes->get('recent', 'NotificationController::recent');
    $routes->post('(:num)/read', 'NotificationController::markRead/$1');
    $routes->post('read-all', 'NotificationController::markAllRead');
});
