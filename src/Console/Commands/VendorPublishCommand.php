<?php

declare(strict_types=1);

namespace Slendie\Console\Commands;

use Slendie\Console\Command;
use Slendie\Foundation\Application;
use Slendie\Foundation\ServiceProvider;

final class VendorPublishCommand extends Command
{
    protected string $signature = 'vendor:publish
        {--tag=* : Grupo a publicar (repetível)}
        {--provider= : Publica os ficheiros deste service provider}
        {--force : Substitui os ficheiros que já existem}
        {--all : Publica tudo o que os packages registaram}';

    protected string $description = 'Publica ficheiros de packages (config, views, stubs...) para o projecto';

    public function handle(Application $app): int
    {
        $tags = array_values(array_filter((array) $this->option('tag'), is_string(...)));
        $provider = $this->option('provider');
        $provider = is_string($provider) && $provider !== '' ? ltrim($provider, '\\') : null;
        $all = $this->option('all') === true;

        if (! $all && $tags === [] && $provider === null) {
            return $this->listAvailable();
        }

        $paths = $this->resolvePaths($all, $tags, $provider);

        if ($paths === null) {
            return self::FAILURE;
        }

        if ($paths === []) {
            $this->warn('Nothing to publish.');

            return self::SUCCESS;
        }

        $failed = false;

        foreach ($paths as $from => $to) {
            $failed = ! $this->publish($app, $from, $to) || $failed;
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @param  list<string>  $tags
     * @return array<string, string>|null null se uma tag ou um provider não existe
     */
    private function resolvePaths(bool $all, array $tags, ?string $provider): ?array
    {
        if ($all) {
            return ServiceProvider::pathsToPublish();
        }

        if ($provider !== null && ! in_array($provider, ServiceProvider::publishableProviders(), true)) {
            $this->error("The provider [{$provider}] has nothing to publish.");

            return null;
        }

        $paths = [];

        if ($tags === []) {
            return ServiceProvider::pathsToPublish($provider);
        }

        foreach ($tags as $tag) {
            if (! in_array($tag, ServiceProvider::publishableGroups(), true)) {
                $this->error("Unable to locate publishable resources for tag [{$tag}].");

                return null;
            }

            $paths = [...$paths, ...ServiceProvider::pathsToPublish($provider, $tag)];
        }

        return $paths;
    }

    private function listAvailable(): int
    {
        $groups = ServiceProvider::publishableGroups();

        if ($groups === []) {
            $this->warn('No publishable resources are registered.');

            return self::SUCCESS;
        }

        sort($groups);

        $this->comment('Tags disponíveis:');
        $this->table(
            ['Tag', 'Ficheiros'],
            array_map(static fn (string $tag): array => [$tag, count(ServiceProvider::pathsToPublish(null, $tag))], $groups),
        );
        $this->line('Usa <info>vendor:publish --tag=<nome></info> (ou --provider=, --all).');

        return self::SUCCESS;
    }

    private function publish(Application $app, string $from, string $to): bool
    {
        if (is_file($from)) {
            return $this->publishFile($app, $from, $to);
        }

        if (! is_dir($from)) {
            $this->error("Can't locate path: [{$from}].");

            return false;
        }

        $ok = true;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($from, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($iterator as $item) {
            if (! $item instanceof \SplFileInfo || ! $item->isFile()) {
                continue;
            }

            $relative = substr($item->getPathname(), strlen(rtrim($from, '/\\')) + 1);
            $ok = $this->publishFile($app, $item->getPathname(), rtrim($to, '/\\').DIRECTORY_SEPARATOR.$relative) && $ok;
        }

        return $ok;
    }

    private function publishFile(Application $app, string $from, string $to): bool
    {
        $label = $this->display($app, $from).'  <muted>→</muted>  '.$this->display($app, $to);

        if (file_exists($to) && $this->option('force') !== true) {
            $this->line("  <comment>SKIPPED</comment> {$label} (exists; use --force)");

            return true;
        }

        $directory = dirname($to);

        if ((! is_dir($directory) && ! mkdir($directory, 0777, true) && ! is_dir($directory)) || ! copy($from, $to)) {
            $this->error("Could not copy [{$from}] to [{$to}].");

            return false;
        }

        $this->line("  <info>COPIED</info>  {$label}");

        return true;
    }

    private function display(Application $app, string $path): string
    {
        $base = rtrim($app->basePath(), '/\\').DIRECTORY_SEPARATOR;

        return str_replace('\\', '/', str_starts_with($path, $base) ? substr($path, strlen($base)) : $path);
    }
}
