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

    public function testCanParseExtends()
    {
        $expected = <<<TPL
<!DOCTYPE html>
<body>
    @yield('content')
</body>
</html>
TPL;
        $loader = new Loader('view', __DIR__ . '/views/', 'tpl.php');
        $loader->parse();
        $actual = $loader->getExtended();

        $this->assertEquals( $actual, $expected );
    }

    public function testCanParseSections()
    {
        $expected = [
            'content' => "This is the content\r\n",
        ];
        $loader = new Loader('view', __DIR__ . '/views/', 'tpl.php');
        $loader->parse();
        $actual = $loader->getSections();

        $this->assertEquals( $actual, $expected );
    }

    public function testCanParseContent()
    {
        $expected = <<<TPL
<!DOCTYPE html>
<body>
    This is the content
</body>
</html>
TPL;
        $loader = new Loader('view', __DIR__ . '/views/', 'tpl.php');
        $loader->parse();
        $content = $loader->getContent();

        $this->assertEquals( $content, $expected );
    }
}