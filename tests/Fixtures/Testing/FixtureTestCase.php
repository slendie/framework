<?php

declare(strict_types=1);

namespace Slendie\Tests\Fixtures\Testing;

use Slendie\Foundation\Application;
use Slendie\Testing\TestCase;

/** TestCase de um projecto Slendie mínimo (framework/tests/_data/testing) para testar o próprio módulo Testing. */
abstract class FixtureTestCase extends TestCase
{
    protected function createApplication(): Application
    {
        /** @var Application $app */
        $app = require fixturePath('testing/bootstrap/app.php');
        $app->useStoragePath(sys_get_temp_dir().DIRECTORY_SEPARATOR.'slendie-testing-'.getmypid());

        return $app;
    }
}
