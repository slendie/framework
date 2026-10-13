<?php

declare(strict_types=1);

namespace Slendie\Console\Commands;

use Slendie\Console\Application as ConsoleApplication;
use Slendie\Console\Command;
use Slendie\Foundation\Application;
use Slendie\Foundation\PackageDiscovery;
use Slendie\Http\HttpMethod;
use Slendie\Http\Kernel;
use Slendie\Routing\Router;
use Slendie\Support\Config;
use Slendie\View\Compiler\BladeCompiler;
use Throwable;

/**
 * Contexto da aplicação para agentes de IA: rotas, schema, config (sanitizada) e info do ambiente.
 */
final class AiContextCommand extends Command
{
    private const array SECTIONS = ['routes', 'schema', 'config', 'info'];

    /** Chaves cujo valor nunca sai (comparação sem distinguir maiúsculas). */
    private const array SENSITIVE = ['key', 'password', 'secret', 'token', 'passwd', 'credential', 'private'];

    protected string $signature = 'ai:context
        {section? : routes, schema, config, info ou all (por omissão)}
        {--json : Emite um único objecto JSON}';

    protected string $description = 'Mostra o contexto da aplicação (rotas, schema, config, info) para agentes de IA';

    public function handle(Application $app): int
    {
        $section = $this->argument('section');
        $section = is_string($section) && $section !== '' ? strtolower($section) : 'all';

        if ($section !== 'all' && ! in_array($section, self::SECTIONS, true)) {
            $this->error("Unknown section [{$section}]. Use: routes, schema, config, info or all.");

            return self::FAILURE;
        }

        $wanted = $section === 'all' ? self::SECTIONS : [$section];
        $data = [];

        foreach ($wanted as $name) {
            $data[$name] = match ($name) {
                'routes' => $this->routes($app),
                'schema' => $this->schema($app),
                'config' => $this->config($app),
                default => $this->environmentInfo($app),
            };
        }

        if ($this->option('json') === true) {
            $this->line((string) json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR));

            return self::SUCCESS;
        }

        foreach ($data as $name => $value) {
            $this->newLine();
            $this->comment('# '.$name);
            $this->render($name, $value);
        }

        $this->newLine();

        return self::SUCCESS;
    }

    // -------------------------------------------------------------------------
    // Secções
    // -------------------------------------------------------------------------

    /**
     * @return list<array{method: string, uri: string, name: string, action: string, middleware: list<string>}>
     */
    private function routes(Application $app): array
    {
        $routes = [];

        foreach ($app->make(Router::class)->getRoutes()->getRoutes() as $route) {
            $routes[] = [
                'method' => implode('|', array_map(static fn (HttpMethod $m): string => $m->value, $route->methods())),
                'uri' => $route->uri(),
                'name' => $route->getName() ?? '',
                'action' => $route->getActionName(),
                'middleware' => array_values(array_map(strval(...), $route->middleware())),
            ];
        }

        return $routes;
    }

    /**
     * @return array<string, list<array{name: string, type: string, nullable: bool, default: ?string}>>
     */
    private function schema(Application $app): array
    {
        try {
            $schema = $app->make('db.schema');
            $tables = $schema->getTables();
            sort($tables);

            $result = [];

            foreach ($tables as $table) {
                $result[$table] = $schema->getColumns($table);
            }

            return $result;
        } catch (Throwable) {
            // Sem base de dados configurada/acessível o schema fica vazio (o agente continua com o resto).
            return [];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function config(Application $app): array
    {
        $config = $app->bound(Config::class) ? $app->make(Config::class)->all() : [];

        return $this->sanitize($config);
    }

    /**
     * @return array<string, mixed>
     */
    private function environmentInfo(Application $app): array
    {
        $config = $app->bound(Config::class) ? $app->make(Config::class) : new Config();
        $default = $config->get('database.default');
        $driver = is_string($default) ? $config->get("database.connections.{$default}.driver") : null;

        $kernel = $app->make(Kernel::class);
        $router = $app->make(Router::class);
        $blade = $app->make(BladeCompiler::class);
        $discovery = new PackageDiscovery();
        $manifest = $discovery->load($app->basePath('vendor'), $app->bootstrapPath('cache/packages.php'));

        $commands = [];

        foreach ($app->make(ConsoleApplication::class)->all() as $name => $command) {
            if (! $command->isHidden()) {
                $commands[] = ['name' => $name, 'description' => $command->getDescription()];
            }
        }

        usort($commands, static fn (array $a, array $b): int => $a['name'] <=> $b['name']);

        $directives = array_keys($blade->getCustomDirectives());
        sort($directives);

        return [
            'slendie' => $app->version(),
            'php' => PHP_VERSION,
            'environment' => $app->environment(),
            'debug' => $app->isDebug(),
            'locale' => $app->getLocale(),
            'database_driver' => is_string($driver) ? $driver : null,
            'providers' => array_keys($app->getLoadedProviders()),
            'packages' => $manifest['packages'],
            'middleware' => [
                'global' => $kernel->getGlobalMiddleware(),
                'groups' => $router->getMiddlewareGroups(),
                'aliases' => $router->getMiddleware(),
            ],
            'blade_directives' => $directives,
            'commands' => $commands,
        ];
    }

    // -------------------------------------------------------------------------
    // Saída em texto
    // -------------------------------------------------------------------------

    private function render(string $name, mixed $value): void
    {
        if ($value === [] || $value === null) {
            $this->line('  (none)', 'muted');

            return;
        }

        if ($name === 'routes' && is_array($value)) {
            foreach ($value as $route) {
                if (! is_array($route)) {
                    continue;
                }

                $middleware = $route['middleware'] ?? [];
                $this->line(sprintf(
                    '  %-8s %-30s %s%s%s',
                    $this->scalar($route['method'] ?? ''),
                    $this->scalar($route['uri'] ?? ''),
                    $this->scalar($route['action'] ?? ''),
                    ($route['name'] ?? '') !== '' ? ' ['.$this->scalar($route['name']).']' : '',
                    is_array($middleware) && $middleware !== [] ? ' ('.implode(', ', array_map($this->scalar(...), $middleware)).')' : '',
                ));
            }

            return;
        }

        if ($name === 'schema' && is_array($value)) {
            foreach ($value as $table => $columns) {
                $this->line('  '.$table);

                foreach (is_array($columns) ? $columns : [] as $column) {
                    if (is_array($column)) {
                        $this->line(sprintf('    %-24s %s%s', $this->scalar($column['name'] ?? ''), $this->scalar($column['type'] ?? ''), ($column['nullable'] ?? false) === true ? ' null' : ''));
                    }
                }
            }

            return;
        }

        foreach ($this->flatten(is_array($value) ? $value : [], '') as $key => $item) {
            $this->line("  {$key} = {$item}");
        }
    }

    /**
     * @param  array<array-key, mixed>  $items
     * @return array<string, string>
     */
    private function flatten(array $items, string $prefix): array
    {
        $flat = [];

        foreach ($items as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;

            if (is_array($value) && $value !== [] && ! array_is_list($value)) {
                $flat = [...$flat, ...$this->flatten($value, $path)];
            } else {
                $flat[$path] = is_array($value) ? (string) json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : $this->scalar($value);
            }
        }

        return $flat;
    }

    private function scalar(mixed $value): string
    {
        return match (true) {
            is_bool($value) => $value ? 'true' : 'false',
            $value === null => 'null',
            is_scalar($value) => (string) $value,
            default => '',
        };
    }

    // -------------------------------------------------------------------------
    // Sanitização
    // -------------------------------------------------------------------------

    /**
     * @param  array<array-key, mixed>  $items
     * @return array<string, mixed>
     */
    private function sanitize(array $items): array
    {
        $clean = [];

        foreach ($items as $key => $value) {
            $key = (string) $key;

            if (is_array($value)) {
                $clean[$key] = $this->sanitize($value);
            } elseif ($this->isSensitive($key) && $value !== null && ! is_bool($value)) {
                $clean[$key] = '********';
            } elseif (is_scalar($value) || $value === null) {
                $clean[$key] = $value;
            } else {
                $clean[$key] = is_object($value) ? $value::class : gettype($value);
            }
        }

        return $clean;
    }

    private function isSensitive(string $key): bool
    {
        $key = strtolower($key);

        foreach (self::SENSITIVE as $needle) {
            if (str_contains($key, $needle)) {
                return true;
            }
        }

        return false;
    }
}
