<?php

declare(strict_types=1);

namespace Slendie\Console\Commands;

use Slendie\Console\Command;
use Slendie\Http\HttpMethod;
use Slendie\Routing\Router;
use Slendie\Support\Str;

final class RouteListCommand extends Command
{
    protected string $signature = 'route:list
        {--method= : Filtra pelo método HTTP}
        {--name= : Filtra pelo nome da rota (contém)}
        {--path= : Filtra pelo URI (contém)}
        {--json : Devolve JSON em vez de tabela}';

    protected string $description = 'Lista as rotas registadas';

    public function handle(Router $router): int
    {
        $method = $this->option('method');
        $name = $this->option('name');
        $path = $this->option('path');

        $routes = [];

        foreach ($router->getRoutes()->getRoutes() as $route) {
            $methods = array_map(static fn (HttpMethod $m): string => $m->value, $route->methods());

            if (is_string($method) && $method !== '' && ! in_array(strtoupper($method), $methods, true)) {
                continue;
            }

            if (is_string($name) && $name !== '' && ! Str::contains($route->getName() ?? '', $name, true)) {
                continue;
            }

            if (is_string($path) && $path !== '' && ! Str::contains($route->uri(), $path, true)) {
                continue;
            }

            $routes[] = [
                'method' => implode('|', $methods),
                'uri' => $route->uri(),
                'name' => $route->getName() ?? '',
                'action' => $route->getActionName(),
                'middleware' => $route->middleware(),
            ];
        }

        if ($this->option('json') === true) {
            $this->line((string) json_encode($routes, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        if ($routes === []) {
            $this->warn("Your application doesn't have any routes matching the given criteria.");

            return self::SUCCESS;
        }

        $this->table(
            ['Method', 'URI', 'Name', 'Action', 'Middleware'],
            array_map(static fn (array $r): array => [$r['method'], $r['uri'], $r['name'], $r['action'], implode(', ', $r['middleware'])], $routes),
        );

        $this->line(count($routes).' route'.(count($routes) === 1 ? '' : 's'), 'muted');

        return self::SUCCESS;
    }
}
