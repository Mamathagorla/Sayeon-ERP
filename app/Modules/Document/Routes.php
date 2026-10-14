<?php

/** @var CodeIgniter\Router\RouteCollection $routes */

$routes->group('documents', ['namespace' => 'App\Modules\Document\Controllers'], static function ($routes) {
    // Company-specific templates (manage = document.edit, same grantees
    // as editing documents: Company Admin / Compliance Officer / Super Admin).
    $routes->get('templates', 'DocumentTemplateController::index', ['filter' => 'permission:document.edit']);
    $routes->get('templates/create', 'DocumentTemplateController::create', ['filter' => 'permission:document.create']);
    $routes->post('templates', 'DocumentTemplateController::store', ['filter' => 'permission:document.create']);
    $routes->get('templates/clone', 'DocumentTemplateController::cloneForm', ['filter' => 'permission:document.create']);
    $routes->post('templates/clone', 'DocumentTemplateController::cloneStore', ['filter' => 'permission:document.create']);
    $routes->get('templates/(:num)/editable', 'DocumentTemplateController::toHtml/$1', ['filter' => 'permission:document.create']);
    $routes->post('templates/(:num)/editable', 'DocumentTemplateController::toHtmlStore/$1', ['filter' => 'permission:document.create']);
    $routes->get('templates/(:num)/edit', 'DocumentTemplateController::edit/$1', ['filter' => 'permission:document.edit']);
    $routes->post('templates/(:num)/edit', 'DocumentTemplateController::saveEdit/$1', ['filter' => 'permission:document.edit']);
    $routes->get('templates/(:num)', 'DocumentTemplateController::view/$1', ['filter' => 'permission:document.edit']);
    $routes->get('templates/(:num)/download', 'DocumentTemplateController::download/$1', ['filter' => 'permission:document.edit']);
    $routes->get('templates/(:num)/map', 'DocumentTemplateController::map/$1', ['filter' => 'permission:document.edit']);
    $routes->post('templates/(:num)/map', 'DocumentTemplateController::saveMap/$1', ['filter' => 'permission:document.edit']);
    $routes->get('templates/(:num)/test', 'DocumentTemplateController::test/$1', ['filter' => 'permission:document.edit']);
    $routes->post('templates/(:num)/accept', 'DocumentTemplateController::accept/$1', ['filter' => 'permission:document.edit']);
    $routes->post('templates/(:num)/activate', 'DocumentTemplateController::activate/$1', ['filter' => 'permission:document.edit']);
    $routes->post('templates/(:num)/deactivate', 'DocumentTemplateController::deactivate/$1', ['filter' => 'permission:document.edit']);
    $routes->post('templates/(:num)/delete', 'DocumentTemplateController::delete/$1', ['filter' => 'permission:document.delete']);

    // Download endpoint for document types without an action in their own
    // module (permission + company scope enforced in the controller).
    $routes->get('render/(:segment)/(:num)', 'DocumentRenderController::show/$1/$2');

    $routes->get('/', 'DocumentController::index', ['filter' => 'permission:document.view']);
    $routes->get('create', 'DocumentController::create', ['filter' => 'permission:document.create']);
    $routes->post('/', 'DocumentController::store', ['filter' => 'permission:document.create']);
    $routes->get('(:num)/edit', 'DocumentController::edit/$1', ['filter' => 'permission:document.edit']);
    $routes->post('(:num)', 'DocumentController::update/$1', ['filter' => 'permission:document.edit']);
    $routes->post('(:num)/delete', 'DocumentController::delete/$1', ['filter' => 'permission:document.delete']);
});
