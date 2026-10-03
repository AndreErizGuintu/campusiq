<?php
/**
 * Front controller. Every request that isn't a real file under public/ lands here.
 */

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Request;
use App\Core\Router;

$request = Request::capture();
$GLOBALS['__request_path'] = $request->path;

$router = new Router();
require BASE_PATH . '/app/routes.php';

$router->dispatch($request)->send();
