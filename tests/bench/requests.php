<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Benchmark informativo (não corre no CI nem faz parte da suite)
|--------------------------------------------------------------------------
| 1000 pedidos GET / a uma view simples, pelo Kernel completo (middleware + router + Blade), sem servidor HTTP.
|
|     php framework/tests/bench/requests.php [pedidos]
|
| Os resultados medidos ficam em docs/performance.md, com as condições da máquina.
*/

use Slendie\Foundation\Application;
use Slendie\Http\Request;
use Slendie\Session\ArraySessionHandler;
use Slendie\Support\Env;

require dirname(__DIR__, 3).'/vendor/autoload.php';

$requests = max(1, (int) ($argv[1] ?? 1000));

Env::set('APP_ENV', 'testing');

/** @var Application $app */
$app = require dirname(__DIR__).'/_data/testing/bootstrap/app.php';
$app->useStoragePath(sys_get_temp_dir().DIRECTORY_SEPARATOR.'slendie-bench');
$app->instance('testing.csrf.disabled', true);
$app->bootstrap();
$app->instance('session.handler', new ArraySessionHandler());

// Aquecimento: compila a view e carrega as classes.
for ($i = 0; $i < 20; $i++) {
    $app->handle(Request::create('/'));
}

gc_collect_cycles();
$memoryBefore = memory_get_usage();
$start = hrtime(true);

for ($i = 0; $i < $requests; $i++) {
    $response = $app->handle(Request::create('/'));

    if ($response->getStatusCode() !== 200) {
        fwrite(STDERR, "Resposta inesperada: {$response->getStatusCode()}\n");
        exit(1);
    }
}

$seconds = (hrtime(true) - $start) / 1e9;

printf(
    "PHP %s (%s) · %s · %d pedidos GET /\n  %.3f s · %.0f req/s · %.3f ms/pedido\n  memória: +%.2f MB (pico %.2f MB)\n",
    PHP_VERSION,
    PHP_SAPI,
    PHP_OS_FAMILY,
    $requests,
    $seconds,
    $requests / $seconds,
    $seconds / $requests * 1000,
    (memory_get_usage() - $memoryBefore) / 1048576,
    memory_get_peak_usage() / 1048576,
);
