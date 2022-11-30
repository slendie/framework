<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Slendie\Framework\View\Loader;

final class LoaderTest extends TestCase
{
    public function testCanDefinePath()
    {
        $loader = new Loader();

        $path = $loader->getBasePath();
        $expected = Loader::convertToPath( SITE_FOLDER . 'resources/views/' );

        $this->assertEquals( $path, $expected );
    }

    public function testCanDefineExtension()
    {
        $loader = new Loader();

        $extension = $loader->getExtension();
        $expected = 'tpl.php';

        $this->assertEquals( $extension, $expected );
    }
}