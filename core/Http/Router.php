<?php
declare(strict_types=1);

namespace Heartbeat\Http;

use Closure;

/**
 * High-performance route dispatcher
 */
final class Router
{
    /** @var array<string, array<int, array{pattern: string, regex: string, handler: mixed}>> */
    private array $routes = [];

    public function get(string $path, mixed $handler): self
    {
        return $this->addRoute('GET', $path, $handler);
    }

    public function post(string $path, mixed $handler): self
    {
        return $this->addRoute('POST', $path, $handler);
    }

    public function any(string $path, mixed $handler): self
    {
        $methods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS'];
        foreach ($methods as $method) {
            $this->addRoute($method, $path, $handler);
        }
        return $this;
    }

    public function addRoute(string $method, string $path, mixed $handler): self
    {
        $normalizedPath = '/' . trim($path, '/');
        if ($normalizedPath === '') {
            $normalizedPath = '/';
        }

        // Convert path with :param into regex pattern
        // e.g., /install/step/:step -> ^/install/step/(?P<step>[^/]+)$
        $regex = preg_replace('#:([a-zA-Z0-9_]+)#', '(?P<$1>[^/]+)', $normalizedPath);
        $regex = '#^' . $regex . '$#';

        $this->routes[strtoupper($method)][] = [
            'pattern' => $normalizedPath,
            'regex'   => $regex,
            'handler' => $handler,
        ];

        return $this;
    }

    /**
     * Dispatch request against registered routes
     */
    public function dispatch(Request $request): Response
    {
        $method = $request->getMethod();
        $path = $request->getPath();

        $routesForMethod = $this->routes[$method] ?? [];

        foreach ($routesForMethod as $route) {
            if (preg_match($route['regex'], $path, $matches)) {
                $params = [];
                foreach ($matches as $key => $value) {
                    if (is_string($key)) {
                        $params[$key] = $value;
                    }
                }

                return $this->executeHandler($route['handler'], $request, $params);
            }
        }

        return Response::html(
            '<!DOCTYPE html><html lang="de"><head><meta charset="utf-8"><title>404 - Seite nicht gefunden</title>' .
            '<style>body{background:#090d16;color:#94a3b8;font-family:sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;}' .
            '.box{text-align:center;padding:2rem;} h1{color:#f97316;font-size:3rem;margin:0 0 1rem;} p{font-size:1.1rem;}' .
            'a{color:#f97316;text-decoration:none;border-bottom:1px solid #f97316;} a:hover{opacity:0.8;}</style></head>' .
            '<body><div class="box"><h1>404</h1><p>Die angeforderte Seite existiert nicht.</p><p><a href="/">Zur Startseite &rarr;</a></p></div></body></html>',
            404
        );
    }

    private function executeHandler(mixed $handler, Request $request, array $params): Response
    {
        if ($handler instanceof Closure) {
            $result = $handler($request, $params);
        } elseif (is_array($handler) && count($handler) === 2) {
            [$class, $method] = $handler;
            $instance = is_string($class) ? new $class() : $class;
            $result = $instance->$method($request, $params);
        } elseif (is_string($handler) && str_contains($handler, '@')) {
            [$class, $method] = explode('@', $handler);
            $instance = new $class();
            $result = $instance->$method($request, $params);
        } else {
            throw new \RuntimeException('Invalid route handler specified.');
        }

        if ($result instanceof Response) {
            return $result;
        }

        if (is_array($result)) {
            return Response::json($result);
        }

        return Response::html((string)$result);
    }
}

