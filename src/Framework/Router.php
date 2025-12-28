<?php

declare(strict_types=1);

namespace Slendie\Framework;

use Slendie\Controllers\Middlewares\AccessMiddleware;
use Slendie\Controllers\Middlewares\AuthMiddleware;
use Slendie\Controllers\Middlewares\WebMiddleware;
use ReflectionException;
use ReflectionMethod;

final class Router
{
    private static ?Router $instance = null;
    private static array $routes = [];
    private array $instanceRoutes;

    private function __construct(array $routes)
    {
        $this->instanceRoutes = $routes;
        self::$routes = $routes;
    }

    /**
     * Obtém a instância única do Router (Singleton)
     *
     * @param array $routes As rotas a serem carregadas (apenas na primeira chamada)
     * @return Router A instância única do Router
     */
    public static function getInstance(array $routes = []): Router
    {
        if (self::$instance === null) {
            // Se não foram fornecidas rotas, tenta carregar do arquivo de configuração
            if (empty($routes) && defined('BASE_PATH')) {
                $routesPath = BASE_PATH . '/config/routes.php';
                if (file_exists($routesPath)) {
                    $routes = require $routesPath;
                }
            }
            self::$instance = new self($routes);
        }
        return self::$instance;
    }

    /**
     * Reseta a instância do singleton (útil para testes)
     */
    public static function resetInstance(): void
    {
        self::$instance = null;
        self::$routes = [];
    }

    /**
     * Previne a clonagem da instância
     */
    private function __clone()
    {
    }

    /**
     * Previne a deserialização da instância
     */
    public function __wakeup()
    {
        throw new \Exception("Cannot unserialize singleton");
    }

    public function dispatch()
    {
        // Cria a instância Request
        $request = new Request();

        $method = $request->method();
        $path = $request->path();

        foreach ($this->instanceRoutes as $route) {
            if (mb_strtoupper($route['method']) !== mb_strtoupper($method)) {
                continue;
            }

            // Tenta fazer match da rota (com ou sem parâmetros)
            $routeParams = $this->matchRoute($route['path'], $path);
            if ($routeParams === null) {
                continue;
            }

            $middlewares = $route['middlewares'] ?? [];

            // Aplica o Slendie\Controllers\Middlewares\WebMiddleware primeiro para injetar a Request
            $webMiddleware = new WebMiddleware();
            if (!$webMiddleware->handle($request)) {
                return;
            }

            // Aplica os outros middlewares
            foreach ($middlewares as $mw) {
                if ($mw === 'auth') {
                    $m = new AuthMiddleware();
                    if (!$m->handle($request)) {
                        return;
                    }
                } elseif (mb_strpos($mw, 'access:') === 0) {
                    $perm = mb_substr($mw, 7);
                    $m = new AccessMiddleware($perm);
                    if (!$m->handle($request)) {
                        return;
                    }
                }
            }

            $handler = $route['handler'];
            if (is_string($handler) && mb_strpos($handler, '@') !== false) {
                list($cls, $meth) = explode('@', $handler, 2);
                $controller = new $cls();

                // Obtém os parâmetros esperados pelo método
                $methodParams = $this->getMethodParameters($cls, $meth);

                // Prepara os argumentos na ordem correta
                $args = [];
                foreach ($methodParams as $paramName) {
                    if (isset($routeParams[$paramName])) {
                        $args[] = $routeParams[$paramName];
                    }
                }

                return call_user_func_array([$controller, $meth], $args);
            }
            if (is_array($handler)) {
                $controller = is_string($handler[0]) ? new $handler[0]() : $handler[0];
                $meth = $handler[1];

                // Obtém os parâmetros esperados pelo método
                $methodParams = $this->getMethodParameters(get_class($controller), $meth);

                // Prepara os argumentos na ordem correta
                $args = [];
                foreach ($methodParams as $paramName) {
                    if (isset($routeParams[$paramName])) {
                        $args[] = $routeParams[$paramName];
                    }
                }

                return call_user_func_array([$controller, $meth], $args);
            }
        }
        http_response_code(404);
        echo 'Not Found';
    }

    /**
     * Converte um padrão de rota com parâmetros em regex e extrai os nomes dos parâmetros
     * Exemplo: /app/membro/{id}/edit -> regex e ['id']
     */
    private function parseRoutePattern(string $pattern): array
    {
        $paramNames = [];
        $parts = [];
        $currentPos = 0;

        // Encontra todos os parâmetros {nome} no padrão
        if (preg_match_all('/\{(\w+)\}/', $pattern, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[0] as $index => $match) {
                $paramName = $matches[1][$index][0];
                $paramNames[] = $paramName;
                $matchStart = $match[1];
                $matchLength = mb_strlen($match[0]);

                // Adiciona a parte antes do parâmetro (escapada)
                if ($matchStart > $currentPos) {
                    $parts[] = preg_quote(mb_substr($pattern, $currentPos, $matchStart - $currentPos), '#');
                }

                // Adiciona o regex de captura para o parâmetro
                $parts[] = '([^/]+)';

                $currentPos = $matchStart + $matchLength;
            }
        }

        // Adiciona a parte final (se houver)
        if ($currentPos < mb_strlen($pattern)) {
            $parts[] = preg_quote(mb_substr($pattern, $currentPos), '#');
        }

        // Se não havia parâmetros, escapa o padrão inteiro
        if (empty($paramNames)) {
            $parts = [preg_quote($pattern, '#')];
        }

        $regex = implode('', $parts);

        return [
            'regex' => '#^' . $regex . '$#',
            'paramNames' => $paramNames
        ];
    }

    /**
     * Verifica se uma rota corresponde ao path e extrai os parâmetros
     */
    private function matchRoute(string $routePattern, string $path): array|null
    {
        // Se a rota não tem parâmetros, compara diretamente
        if (mb_strpos($routePattern, '{') === false) {
            return $routePattern === $path ? [] : null;
        }

        // Se tem parâmetros, usa regex
        $parsed = $this->parseRoutePattern($routePattern);
        if (preg_match($parsed['regex'], $path, $matches) === 1) {
            array_shift($matches); // Remove o match completo, mantém apenas os grupos
            return array_combine($parsed['paramNames'], $matches);
        }

        return null;
    }

    /**
     * Obtém os parâmetros do método do controller usando Reflection
     */
    private function getMethodParameters(string $className, string $methodName): array
    {
        try {
            $reflection = new ReflectionMethod($className, $methodName);
            $params = [];
            foreach ($reflection->getParameters() as $param) {
                $params[] = $param->getName();
            }
            return $params;
        } catch (ReflectionException $e) {
            return [];
        }
    }

    /**
     * Verifica se uma rota existe pelo nome
     *
     * @param string $name O nome da rota
     * @return bool True se a rota existe, false caso contrário
     */
    public static function hasRoute(string $name): bool
    {
        // Usa self::$routes se disponível (definido quando Router é inicializado)
        // Mas se estiver vazio, tenta carregar do arquivo diretamente (como a função route() faz)
        if (empty(self::$routes)) {
            if (defined('BASE_PATH')) {
                $routesPath = BASE_PATH . '/config/routes.php';
                if (file_exists($routesPath)) {
                    self::$routes = require $routesPath;
                } else {
                    return false;
                }
            } else {
                return false;
            }
        }

        // Procura a rota pelo nome
        foreach (self::$routes as $route) {
            if (isset($route['name']) && $route['name'] === $name) {
                return true;
            }
        }

        return false;
    }
}
