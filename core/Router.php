<?php

namespace Platform;

use Platform\Http\Request;

class Router
{
    private array $routes = [];

    public function get(string $pattern, callable $handler): void
    {
        $this->routes[] = ['GET', $pattern, $handler];
    }

    public function post(string $pattern, callable $handler): void
    {
        $this->routes[] = ['POST', $pattern, $handler];
    }

    public function any(string $pattern, callable $handler): void
    {
        $this->routes[] = ['*', $pattern, $handler];
    }

    public function dispatch(Request $request): void
    {
        $method = $request->method();
        $path = $request->path();

        foreach ($this->routes as [$routeMethod, $pattern, $handler]) {
            if ($routeMethod !== '*' && $routeMethod !== $method) {
                continue;
            }

            $params = $this->match($pattern, $path);
            if ($params !== false) {
                $handler($request, $params);
                return;
            }
        }

        http_response_code(404);
        echo '404 Not Found';
    }

    private function match(string $pattern, string $path): array|false
    {
        // Convert {param} to named capture groups
        $regex = preg_replace('#\{(\w+)\}#', '(?P<$1>[^/]+)', $pattern);
        // Support {file*} for catch-all
        $regex = preg_replace('#\{(\w+)\*\}#', '(?P<$1>.+)', $regex);
        $regex = '#^' . $regex . '$#';

        if (preg_match($regex, $path, $matches)) {
            return array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
        }

        return false;
    }
}
