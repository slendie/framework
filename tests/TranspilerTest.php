<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Slendie\Framework\Environment\Environment;
use Slendie\Framework\View\Transpiler;

final class TranspilerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $env = Environment::getInstance();
        $env->setEnvFile( SITE_FOLDER . '.env.testing' );
        $env->forceLoad();
    }

    public function testCanTranspileIf()
    {
        $content = file_get_contents(__DIR__ . '/views/transpiler_if.tpl.php');
        $expected = file_get_contents(__DIR__ . '/views/transpiler_if_expected.tpl.php');
        $transpiler = new Transpiler( $content );
        $transpiler->parse();
        $actual = $transpiler->getContent();

        $this->assertEquals( $actual, $expected );
    }

    public function testCanTranspileFor()
    {
        $content = file_get_contents(__DIR__ . '/views/transpiler_for.tpl.php');
        $expected = file_get_contents(__DIR__ . '/views/transpiler_for_expected.tpl.php');
        $transpiler = new Transpiler( $content );
        $transpiler->parse();
        $actual = $transpiler->getContent();

        $this->assertEquals( $actual, $expected );
    }

    public function testCanTranspileForEach()
    {
        $content = file_get_contents(__DIR__ . '/views/transpiler_foreach.tpl.php');
        $expected = file_get_contents(__DIR__ . '/views/transpiler_foreach_expected.tpl.php');
        $transpiler = new Transpiler( $content );
        $transpiler->parse();
        $actual = $transpiler->getContent();

        $this->assertEquals( $actual, $expected );
    }


    public function testCanTranspileWhile()
    {
        $content = file_get_contents(__DIR__ . '/views/transpiler_while.tpl.php');
        $expected = file_get_contents(__DIR__ . '/views/transpiler_while_expected.tpl.php');
        $transpiler = new Transpiler($content);
        $transpiler->parse();
        $actual = $transpiler->getContent();

        $this->assertEquals($actual, $expected);
    }

    public function testCanTranspilePhp()
    {
        $content = file_get_contents(__DIR__ . '/views/transpiler_php.tpl.php');
        $expected = file_get_contents(__DIR__ . '/views/transpiler_php_expected.tpl.php');
        $transpiler = new Transpiler($content);
        $transpiler->parse();
        $actual = $transpiler->getContent();

        $this->assertEquals($actual, $expected);
    }

    public function testCanTranspilePhpBlock()
    {
        $content = file_get_contents(__DIR__ . '/views/transpiler_php_block.tpl.php');
        $expected = file_get_contents(__DIR__ . '/views/transpiler_php_block_expected.tpl.php');
        $transpiler = new Transpiler($content);
        $transpiler->parse();
        $actual = $transpiler->getContent();

        $this->assertEquals($actual, $expected);
    }

    public function testCanTranspileAsset()
    {
        $content = file_get_contents(__DIR__ . '/views/transpiler_asset.tpl.php');
        $expected = file_get_contents(__DIR__ . '/views/transpiler_asset_expected.tpl.php');
        $transpiler = new Transpiler($content);
        $transpiler->parse();
        $actual = $transpiler->getContent();

        $this->assertEquals($actual, $expected);
    }

    public function testCanTranspileRoute()
    {
        $content = file_get_contents(__DIR__ . '/views/transpiler_route.tpl.php');
        $expected = file_get_contents(__DIR__ . '/views/transpiler_route_expected.tpl.php');
        $transpiler = new Transpiler($content);
        $transpiler->parse();
        $actual = $transpiler->getContent();

        $this->assertEquals($actual, $expected);
    }

    public function testCanTranspileEcho()
    {
        $content = file_get_contents(__DIR__ . '/views/transpiler_echo.tpl.php');
        $expected = file_get_contents(__DIR__ . '/views/transpiler_echo_expected.tpl.php');
        $transpiler = new Transpiler($content);
        $transpiler->parse();
        $actual = $transpiler->getContent();

        $this->assertEquals($actual, $expected);
    }
}