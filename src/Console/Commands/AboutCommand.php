<?php

declare(strict_types=1);

namespace Slendie\Console\Commands;

use Slendie\Console\Command;
use Slendie\Foundation\Application;
use Slendie\Foundation\PackageDiscovery;
use Slendie\Support\Config;

final class AboutCommand extends Command
{
    protected string $signature = 'about';

    protected string $description = 'Mostra informação sobre a aplicação';

    public function handle(Application $app, Config $config): int
    {
        $sections = [
            'Ambiente' => [
                'Aplicação' => $this->text($config->get('app.name')),
                'Slendie' => $app->version(),
                'PHP' => PHP_VERSION,
                'Ambiente' => $app->environment(),
                'Debug' => $app->isDebug() ? '<comment>ON</comment>' : '<info>OFF</info>',
                'URL' => $this->text($config->get('app.url')),
                'Timezone' => date_default_timezone_get(),
                'Locale' => $app->getLocale(),
            ],
            'Base de dados' => [
                'Ligação' => $this->connection($config),
            ],
        ];

        $providers = array_keys($app->getLoadedProviders());
        $sections['Providers'] = $providers === [] ? ['—' => ''] : array_fill_keys($providers, '');
        $packages = (new PackageDiscovery())->load($app->basePath('vendor'), $app->bootstrapPath('cache/packages.php'))['packages'];
        $sections['Packages'] = $packages === []
            ? ['Descobertos' => 'nenhum']
            : array_map(static fn (array $providers): string => implode(', ', $providers), $packages);

        foreach ($sections as $title => $items) {
            $this->newLine();
            $this->comment($title);

            $width = max(array_map(static fn (int|string $label): int => strlen((string) $label), array_keys($items)));

            foreach ($items as $label => $value) {
                $this->line($value === '' ? "  {$label}" : '  '.str_pad((string) $label, $width + 2).$value);
            }
        }

        $this->newLine();

        return self::SUCCESS;
    }

    private function text(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '—';
    }

    private function connection(Config $config): string
    {
        $default = $this->text($config->get('database.default'));
        $driver = $config->get("database.connections.{$default}.driver");

        return is_string($driver) && $driver !== $default ? "{$default} ({$driver})" : $default;
    }
}
