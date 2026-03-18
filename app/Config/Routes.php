<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->match(['get', 'post'], '/', 'Home::index');
$routes->match(['get', 'post'], 'login', 'Home::index');

// Dashboard (after login)
$routes->match(['get', 'post'], 'dashboard', 'Home::dashboard');
$routes->post('dashboard/uber-sandbox-token', 'Home::uberSandboxToken');
$routes->get('logout', 'Home::logout');

// Website Order API
$routes->post('api/orders', 'Orders::create');
$routes->get('api/orders', 'Orders::index');
$routes->patch('api/orders/(:num)/status', 'Orders::updateStatus/$1');

// Uber Eats marketplace webhook
$routes->post('webhook/uber-eats/orders', 'UberWebhook::uberEatsOrders');

// Uber Direct delivery status webhook
$routes->post('webhook/uber-direct/status', 'UberWebhook::uberDirectStatus');
