<?php
/**
 * All routes. Third argument = roles allowed (empty = public). Every POST is CSRF checked by the router.
 *
 * @var App\Core\Router $router
 */

use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\HomeController;
use App\Controllers\PortalController;

const STAFF = ['staff'];
const PORTAL = ['student', 'parent'];
const ANY_USER = ['staff', 'student', 'parent'];

// Public
$router->get('/', [HomeController::class, 'index']);
$router->get('/login', [AuthController::class, 'showLogin']);
$router->post('/login', [AuthController::class, 'login']);
$router->get('/signup', [AuthController::class, 'showSignup']);
$router->post('/signup', [AuthController::class, 'signup']);
$router->post('/logout', [AuthController::class, 'logout'], ANY_USER);

// Staff
$router->get('/dashboard', [DashboardController::class, 'index'], STAFF);

// Student / parent portal
$router->get('/my/records', [PortalController::class, 'records'], PORTAL);
