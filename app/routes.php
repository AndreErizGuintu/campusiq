<?php
/**
 * All routes. Third argument = roles allowed (empty = public). Every POST is CSRF checked by the router.
 *
 * @var App\Core\Router $router
 */

use App\Controllers\AiController;
use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\EmailController;
use App\Controllers\HomeController;
use App\Controllers\PortalController;
use App\Controllers\RecordController;
use App\Controllers\ReportController;
use App\Controllers\StudentController;

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
$router->get('/students', [StudentController::class, 'index'], STAFF);
$router->get('/students/{id}', [StudentController::class, 'show'], STAFF);
$router->post('/students/{id}/records', [RecordController::class, 'store'], STAFF);
$router->post('/records/{id}', [RecordController::class, 'update'], STAFF);
$router->post('/records/{id}/delete', [RecordController::class, 'destroy'], STAFF);

// API 1: AI Assistant (Gemini, server side)
$router->get('/ai', [AiController::class, 'index'], STAFF);
$router->post('/api/ai/ask', [AiController::class, 'ask'], STAFF);

// API 2: Email Alerts (EmailJS in the browser; the server builds content and logs results)
$router->get('/emails', [EmailController::class, 'index'], STAFF);
$router->get('/api/email/compose', [EmailController::class, 'compose'], STAFF);
$router->get('/api/email/logs/{id}', [EmailController::class, 'show'], STAFF);
$router->post('/api/email/log', [EmailController::class, 'log'], STAFF);
$router->post('/api/email/triggers', [EmailController::class, 'triggers'], STAFF);

// API 3: PDF Reports (PDFShift, server side). Files only through routes that check ownership.
$router->get('/reports', [ReportController::class, 'index'], STAFF);
$router->post('/api/reports', [ReportController::class, 'generate'], ANY_USER);
$router->get('/reports/{id}/preview', [ReportController::class, 'preview'], ANY_USER);
$router->get('/reports/{id}/download', [ReportController::class, 'download'], ANY_USER);

// Student / parent portal
$router->get('/my/records', [PortalController::class, 'records'], PORTAL);
