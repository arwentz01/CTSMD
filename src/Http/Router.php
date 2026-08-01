<?php

declare(strict_types=1);

namespace App\Http;

final class Router
{
    /** @var array<string, array<string, callable>> */
    private array $routes = [];

    public function get(string $path, callable $handler): void
    {
        $this->routes['GET'][$this->normalize($path)] = $handler;
    }

    public function post(string $path, callable $handler): void
    {
        $this->routes['POST'][$this->normalize($path)] = $handler;
    }

    public function dispatch(string $method, string $uri): mixed
    {
        $parsedPath = parse_url($uri, PHP_URL_PATH);
        $path = $this->normalize(is_string($parsedPath) ? $parsedPath : '/');
        $method = strtoupper($method);
        $lookupMethod = $method === 'HEAD' ? 'GET' : $method;
        $handler = $this->routes[$lookupMethod][$path] ?? null;

        if ($handler === null) {
            return null;
        }

        return $handler();
    }

    private function normalize(string $path): string
    {
        $path = '/' . trim($path, '/');
        return $path === '/' ? '/' : rtrim($path, '/');
    }
}
