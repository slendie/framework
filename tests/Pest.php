<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Pest — framework/ isolado
|--------------------------------------------------------------------------
| Usado quando o package é testado sozinho (fora do monorepo). No monorepo,
| o ficheiro tests/Pest.php da raiz já aplica o mesmo setup.
*/

use Slendie\Container\Container;
use Slendie\Support\Env;
use Slendie\Support\Facade;
use Slendie\Testing\TestCase as TestingTestCase;

pest()->beforeEach(function (): void {
    // Os testes baseados em Slendie\Testing\TestCase gerem o seu próprio estado global.
    if ($this instanceof TestingTestCase) {
        return;
    }

    Container::setInstance(null);
    Facade::setFacadeApplication(null);
    Facade::clearResolvedInstances();
    Env::flush();
})->in(__DIR__);

if (! function_exists('fixturePath')) {
    function fixturePath(string $path = ''): string
    {
        return __DIR__.'/_data'.($path === '' ? '' : '/'.ltrim($path, '/'));
    }
}
