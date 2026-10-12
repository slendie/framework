<?php

declare(strict_types=1);

namespace Slendie\Console\Commands;

use Slendie\Console\Command;
use Slendie\Foundation\Application;

final class ServeCommand extends Command
{
    protected string $signature = 'serve
        {--host=127.0.0.1 : Endereço onde escutar}
        {--port=8000 : Porta (se estiver ocupada tenta a seguinte)}
        {--tries=10 : Quantas portas tentar}';

    protected string $description = 'Arranca o servidor de desenvolvimento (php -S)';

    public function handle(Application $app): int
    {
        $host = (string) $this->option('host');
        $port = (string) $this->option('port');
        $tries = (string) $this->option('tries');

        if ($host === '' || ! ctype_digit($port) || ! ctype_digit($tries) || (int) $port < 1 || (int) $port > 65535) {
            $this->error('Opções inválidas: --host não pode ser vazio e --port/--tries têm de ser inteiros positivos.');

            return self::INVALID;
        }

        $port = $this->firstFreePort($host, (int) $port, max(1, (int) $tries));

        if ($port === null) {
            $this->error("Não há nenhuma porta livre em {$host} (tentadas {$tries}).");

            return self::FAILURE;
        }

        $this->badge('INFO', "Server running on [http://{$host}:{$port}].");
        $this->line('Press Ctrl+C to stop the server', 'muted');
        $this->newLine();

        $process = proc_open(
            $this->buildCommand($host, $port),
            [0 => STDIN, 1 => STDOUT, 2 => STDERR],
            $pipes,
            $app->basePath(),
            [...$this->environment(), 'APP_ENV' => $app->environment()],
        );

        if (! is_resource($process)) {
            $this->error('Não foi possível arrancar o servidor PHP.');

            return self::FAILURE;
        }

        do {
            usleep(200_000);
            $status = proc_get_status($process);
        } while ($status['running']);

        proc_close($process);

        return $status['exitcode'] > 0 ? $status['exitcode'] : self::SUCCESS;
    }

    /**
     * O comando `php -S host:port -t public`.
     *
     * @return list<string>
     */
    public function buildCommand(string $host, int $port): array
    {
        $public = $this->container() instanceof Application ? $this->container()->publicPath() : 'public';

        return [PHP_BINARY, '-S', "{$host}:{$port}", '-t', $public];
    }

    /** A primeira porta (a partir de $port) onde ninguém está à escuta. */
    private function firstFreePort(string $host, int $port, int $tries): ?int
    {
        for ($i = 0; $i < $tries && $port + $i <= 65535; $i++) {
            $connection = @stream_socket_client('tcp://'.$host.':'.($port + $i), $errno, $error, 0.2);

            if ($connection === false) {
                return $port + $i;
            }

            fclose($connection);
        }

        return null;
    }

    /** @return array<string, string> */
    private function environment(): array
    {
        $env = getenv();

        return array_filter($env, is_string(...));
    }
}
