<?php

declare(strict_types=1);

/*
 * Script de fumo da peça 01 (não é teste Pest):
 *   php framework/tests/smoke.php
 */

require dirname(__DIR__, 2).'/vendor/autoload.php';

use Slendie\Foundation\Application;
use Slendie\Tests\Fixtures\GreeterContract;

$app = new Application(__DIR__.'/_data/app');
$app->bootstrapWith();

echo 'Slendie ', $app->version(), PHP_EOL;
echo 'app.name     = ', config('app.name'), PHP_EOL;
echo 'environment  = ', $app->environment(), PHP_EOL;
echo 'timezone     = ', date_default_timezone_get(), PHP_EOL;
echo 'providers    = ', implode(', ', array_map('class_basename', array_keys($app->getLoadedProviders()))), PHP_EOL;
echo 'greeter      = ', $app->make(GreeterContract::class)->greet('Slendie'), PHP_EOL;
echo 'storage_path = ', storage_path('logs'), PHP_EOL;
echo PHP_EOL, 'OK', PHP_EOL;
