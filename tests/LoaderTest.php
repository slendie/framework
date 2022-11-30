<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Slendie\Framework\View\Loader;

final class LoaderTest extends TestCase
{
    public function testCanDefinePath()
    {
        $loader = new Loader();

        $path = $loader->getBasePath();
        $expected = SITE_FOLDER . '/resources/views/';
//        $expected = str_replace('\\', \DIRECTORY_SEPARATOR, dirname( __DIR__, 2 ) . '/resources/views/');
//        $expected = str_replace('/', \DIRECTORY_SEPARATOR, $expected);

        $this->assertEquals( $path, $expected );
    }
}