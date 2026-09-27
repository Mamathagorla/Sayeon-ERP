<?php

/** @var CodeIgniter\Router\RouteCollection $routes */

$routes->group('auth', ['namespace' => 'App\Modules\Auth\Controllers'], static function ($routes) {
    $routes->get('login', 'AuthController::login');
    $routes->post('attempt-login', 'AuthController::attemptLogin');
    $routes->get('logout', 'AuthController::logout');

    $routes->get('profile', 'AuthController::profile');
    $routes->post('profile', 'AuthController::updateProfile');
    $routes->post('change-password', 'AuthController::changePassword');

    // Dev-only account switcher — see AuthController::switchProfile() for the environment guard.
    $routes->get('switch-profile', 'AuthController::switchProfile');
    $routes->post('switch-profile/(:num)', 'AuthController::doSwitchProfile/$1');

    // User & Role management (Super Admin only)
    $routes->get('users', 'UserController::index', ['filter' => 'permission:user.view']);
    $routes->get('users/create', 'UserController::create', ['filter' => 'permission:user.create']);
    $routes->post('users', 'UserController::store', ['filter' => 'permission:user.create']);
    $routes->get('users/(:num)/edit', 'UserController::edit/$1', ['filter' => 'permission:user.edit']);
    $routes->post('users/(:num)', 'UserController::update/$1', ['filter' => 'permission:user.edit']);
    $routes->post('users/(:num)/delete', 'UserController::delete/$1', ['filter' => 'permission:user.delete']);

    $routes->get('roles', 'RoleController::index', ['filter' => 'permission:role.view']);
    $routes->get('roles/(:num)/edit', 'RoleController::edit/$1', ['filter' => 'permission:role.edit']);
    $routes->post('roles/(:num)', 'RoleController::update/$1', ['filter' => 'permission:role.edit']);
});
