<?php
declare(strict_types=1);

namespace App\Core;

use App\Exceptions\BusinessRuleException;
use App\Exceptions\ValidationException;
use Throwable;

/**
 * Router sederhana. Handler: callable(array $params, array $body, array $query): Response
 * Pola path mendukung placeholder, mis. /loans/{id}/return.
 */
final class Router
{
    /** @var array<int, array{method:string, regex:string, handler:callable}> */
    private array $routes = [];

    public function add(string $method, string $pattern, callable $handler): self
    {
        $pattern = rtrim($pattern, '/') ?: '/';
        $regex = '#^' . preg_replace('#\{([a-zA-Z_]+)\}#', '(?P<$1>[^/]+)', $pattern) . '/?$#';
        $this->routes[] = ['method' => strtoupper($method), 'regex' => $regex, 'handler' => $handler];
        return $this;
    }

    public function get(string $p, callable $h): self { return $this->add('GET', $p, $h); }
    public function post(string $p, callable $h): self { return $this->add('POST', $p, $h); }
    public function put(string $p, callable $h): self { return $this->add('PUT', $p, $h); }
    public function patch(string $p, callable $h): self { return $this->add('PATCH', $p, $h); }
    public function delete(string $p, callable $h): self { return $this->add('DELETE', $p, $h); }

    public function dispatch(string $method, string $path, array $query = [], array $body = []): Response
    {
        try {
            $method = strtoupper($method);
            $path = '/' . trim($path, '/');
            $pathMatched = false;

            foreach ($this->routes as $route) {
                if (!preg_match($route['regex'], $path, $m)) {
                    continue;
                }
                $pathMatched = true;
                if ($route['method'] !== $method) {
                    continue;
                }
                $params = array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY);
                return ($route['handler'])($params, $body, $query);
            }

            return $pathMatched
                ? Response::error('Method tidak diizinkan', 405)
                : Response::error('Endpoint tidak ditemukan', 404);
        } catch (ValidationException $e) {
            return Response::error($e->getMessage(), 422, $e->getErrors());
        } catch (BusinessRuleException $e) {
            return Response::error($e->getMessage(), $e->getHttpStatus());
        } catch (Throwable $e) {
            error_log('[PustakaKu] ' . $e::class . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
            return Response::error('Terjadi kesalahan pada server', 500);
        }
    }
}
