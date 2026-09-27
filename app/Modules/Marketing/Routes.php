<?php

/** @var CodeIgniter\Router\RouteCollection $routes */

$routes->group('campaigns', ['namespace' => 'App\Modules\Marketing\Controllers'], static function ($routes) {
    $routes->get('/', 'CampaignController::index', ['filter' => 'permission:campaign.view']);
    $routes->get('create', 'CampaignController::create', ['filter' => 'permission:campaign.create']);
    $routes->post('/', 'CampaignController::store', ['filter' => 'permission:campaign.create']);
    $routes->get('(:num)', 'CampaignController::show/$1', ['filter' => 'permission:campaign.view']);
    $routes->get('(:num)/edit', 'CampaignController::edit/$1', ['filter' => 'permission:campaign.edit']);
    $routes->post('(:num)', 'CampaignController::update/$1', ['filter' => 'permission:campaign.edit']);
    $routes->post('(:num)/delete', 'CampaignController::delete/$1', ['filter' => 'permission:campaign.delete']);
});
