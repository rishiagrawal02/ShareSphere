<?php

declare(strict_types=1);

namespace App\Http;

use App\Http\Exceptions\MethodNotAllowedException;
use App\Http\Exceptions\NotFoundException;

class Router
{
    private array $routes = [];
    private array $globalMiddleware = [];

    public function get(string $path, callable|array|string $handler, array $middleware = []): void
    {
        $this->addRoute('GET', $path, $handler, $middleware);
    }

    public function post(string $path, callable|array|string $handler, array $middleware = []): void
    {
        $this->addRoute('POST', $path, $handler, $middleware);
    }

    public function put(string $path, callable|array|string $handler, array $middleware = []): void
    {
        $this->addRoute('PUT', $path, $handler, $middleware);
    }

    public function patch(string $path, callable|array|string $handler, array $middleware = []): void
    {
        $this->addRoute('PATCH', $path, $handler, $middleware);
    }

    public function delete(string $path, callable|array|string $handler, array $middleware = []): void
    {
        $this->addRoute('DELETE', $path, $handler, $middleware);
    }

    public function addRoute(string $method, string $path, callable|array|string $handler, array $middleware = []): void
    {
        $normalizedPath = '/' . trim($path, '/');
        if ($normalizedPath === '//') {
            $normalizedPath = '/';
        }

        $pattern = preg_replace_callback('/\{([a-zA-Z0-9_]+)\}/', function ($matches) {
            $paramName = $matches[1];
            if ($paramName === 'id' || str_ends_with($paramName, 'Id')) {
                return '(?P<' . $paramName . '>\d+)';
            }
            return '(?P<' . $paramName . '>[^/]+)';
        }, $normalizedPath);

        $regex = '#^' . $pattern . '$#';

        $this->routes[] = [
            'method' => strtoupper($method),
            'path' => $normalizedPath,
            'regex' => $regex,
            'handler' => $handler,
            'middleware' => $middleware,
        ];
    }

    public function use(callable|object|string $middleware): void
    {
        $this->globalMiddleware[] = $middleware;
    }

    public function dispatch(Request $request): Response
    {
        $requestMethod = $request->getMethod();
        $requestPath = $request->getPath();

        $matchedRoutesByPath = [];
        $matchedRoute = null;
        $extractedParams = [];

        foreach ($this->routes as $route) {
            if (preg_match($route['regex'], $requestPath, $matches)) {
                $matchedRoutesByPath[] = $route['method'];
                if ($route['method'] === $requestMethod) {
                    $matchedRoute = $route;
                    foreach ($matches as $key => $val) {
                        if (is_string($key)) {
                            $extractedParams[$key] = ctype_digit($val) ? (int) $val : $val;
                        }
                    }
                    break;
                }
            }
        }

        if ($matchedRoute === null) {
            if (!empty($matchedRoutesByPath)) {
                $allowed = array_unique($matchedRoutesByPath);
                sort($allowed);
                throw new MethodNotAllowedException($allowed);
            }
            throw new NotFoundException("Route not found: {$requestPath}");
        }

        foreach ($extractedParams as $k => $v) {
            $request->setAttribute($k, $v);
        }

        $allMiddleware = array_merge($this->globalMiddleware, $matchedRoute['middleware']);
        $handler = $matchedRoute['handler'];

        return $this->runPipeline($allMiddleware, $handler, $request, $extractedParams);
    }

    private function runPipeline(array $middlewareList, callable|array|string $handler, Request $request, array $params): Response
    {
        $pipeline = array_reduce(
            array_reverse($middlewareList),
            function ($next, $middleware) {
                return function (Request $req) use ($middleware, $next): Response {
                    if (is_string($middleware) && class_exists($middleware)) {
                        $instance = new $middleware();
                        return $instance->handle($req, $next);
                    }
                    if (is_object($middleware) && method_exists($middleware, 'handle')) {
                        return $middleware->handle($req, $next);
                    }
                    if (is_callable($middleware)) {
                        return $middleware($req, $next);
                    }
                    return $next($req);
                };
            },
            function (Request $req) use ($handler, $params): Response {
                if (is_callable($handler)) {
                    return $handler($req, ...array_values($params));
                }

                if (is_array($handler) && count($handler) === 2) {
                    [$class, $method] = $handler;
                    $controller = is_string($class) ? new $class() : $class;
                    return $controller->$method($req, ...array_values($params));
                }

                if (is_string($handler) && str_contains($handler, '@')) {
                    [$class, $method] = explode('@', $handler, 2);
                    $controller = new $class();
                    return $controller->$method($req, ...array_values($params));
                }

                throw new \RuntimeException('Invalid route handler configuration');
            }
        );

        return $pipeline($request);
    }
}
