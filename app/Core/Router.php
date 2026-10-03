<?php

namespace App\Core;

/**
 * Minimal router: GET/POST routes with {id} params, role checks and CSRF on every POST.
 */
class Router
{
    private array $routes = [];

    /** @param string[] $roles empty = public, otherwise the roles allowed in */
    public function get(string $path, array $handler, array $roles = []): void
    {
        $this->add('GET', $path, $handler, $roles);
    }

    public function post(string $path, array $handler, array $roles = []): void
    {
        $this->add('POST', $path, $handler, $roles);
    }

    private function add(string $method, string $path, array $handler, array $roles): void
    {
        $pattern = preg_replace('#\{(\w+)\}#', '(?P<$1>\d+)', rtrim($path, '/') ?: '/');
        $this->routes[] = [
            'method' => $method,
            'regex' => '#^' . $pattern . '$#',
            'handler' => $handler,
            'roles' => $roles,
        ];
    }

    public function dispatch(Request $request): Response
    {
        $this->ageFormState();
        $pathMatched = false;

        foreach ($this->routes as $route) {
            if (!preg_match($route['regex'], $request->path, $matches)) {
                continue;
            }
            $pathMatched = true;
            if ($route['method'] !== $request->method) {
                continue;
            }

            if ($route['roles']) {
                if (!Auth::check()) {
                    if ($request->wantsJson()) {
                        return Response::json(['ok' => false, 'error' => 'Please log in again.'], 401);
                    }
                    if ($request->method === 'GET') {
                        $_SESSION['_intended'] = $request->path;
                    }
                    flash('info', 'Please log in to continue.');
                    return Response::redirect('/login');
                }
                if (!Auth::is(...$route['roles'])) {
                    Response::error(403);
                }
            }

            if ($request->isPost() && !Csrf::verify($request)) {
                // Apache turns unknown codes like 419 into 500, so a stale token is a 403.
                Response::error(403, null, 'The form was open too long. Go back, refresh the page and try again.', 'Your session expired');
            }

            $params = array_map('intval', array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY));
            [$class, $method] = $route['handler'];
            $result = (new $class())->{$method}($request, ...array_values($params));

            return $result instanceof Response ? $result : Response::html((string) $result);
        }

        Response::error($pathMatched ? 405 : 404);
    }

    /**
     * Validation errors and old input survive exactly one redirect:
     * values stored as "_next_*" during a POST become current on the next request.
     */
    private function ageFormState(): void
    {
        foreach (['errors', 'old'] as $key) {
            $_SESSION["_{$key}"] = $_SESSION["_next_{$key}"] ?? [];
            unset($_SESSION["_next_{$key}"]);
        }
    }
}
