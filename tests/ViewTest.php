<?php

use PHPUnit\Framework\TestCase;
use Slendie\Framework\View\View;

class ViewTest extends TestCase
{
    public function testCanRenderView()
    {
        $expected = <<<TPL
<!DOCTYPE html>
<body>
    <h1>This is the title</h1>
    <p>This is the content</p>
</body>
</html>


TPL;

        $view = new View('view_with_data', __DIR__ . '/views/', 'tpl.php', __DIR__ . '/cache/');
        $view->setData([
            'title'     => 'This is the title',
            'content'   => 'This is the content',
        ]);
        $actual = $view->render();

        $this->assertEquals( $actual, $expected );
    }
}