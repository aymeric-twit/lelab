<?php

namespace Platform;

use Platform\Http\Middleware\Middleware;
use Platform\Http\Request;

class Router
{
    private array $routes = [];

    /** @var Middleware[] Middleware actifs pour le group() en cours */
    private array $groupMiddleware = [];

    public function get(string $pattern, callable $handler): void
    {
        $this->routes[] = ['GET', $pattern, $handler, $this->groupMiddleware];
    }

    public function post(string $pattern, callable $handler): void
    {
        $this->routes[] = ['POST', $pattern, $handler, $this->groupMiddleware];
    }

    public function any(string $pattern, callable $handler): void
    {
        $this->routes[] = ['*', $pattern, $handler, $this->groupMiddleware];
    }

    /**
     * Enregistre un groupe de routes partageant les mêmes middleware.
     *
     * @param Middleware[] $middleware
     */
    public function group(array $middleware, callable $callback): void
    {
        $previous = $this->groupMiddleware;
        $this->groupMiddleware = array_merge($this->groupMiddleware, $middleware);
        $callback($this);
        $this->groupMiddleware = $previous;
    }

    public function dispatch(Request $request): void
    {
        $method = $request->method();
        $path = $request->path();

        foreach ($this->routes as [$routeMethod, $pattern, $handler, $middleware]) {
            if ($routeMethod !== '*' && $routeMethod !== $method) {
                continue;
            }

            $params = $this->match($pattern, $path);
            if ($params !== false) {
                $this->executerAvecMiddleware($request, $params, $handler, $middleware);
                return;
            }
        }

        http_response_code(404);
        echo '404 Not Found';
    }

    /**
     * @param Middleware[] $middleware
     */
    private function executerAvecMiddleware(Request $request, array $params, callable $handler, array $middleware): void
    {
        if ($middleware === []) {
            $handler($request, $params);
            return;
        }

        // Construire la pipeline : chaque middleware appelle le suivant
        $pipeline = array_reduce(
            array_reverse($middleware),
            fn (\Closure $next, Middleware $mw) => fn (Request $req) => $mw->handle($req, $next),
            fn (Request $req) => $handler($req, $params)
        );

        $pipeline($request);
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
