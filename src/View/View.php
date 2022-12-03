<?php

namespace Slendie\Framework\View;

use Slendie\Framework\View\Loader;

class View
{

    protected $content = '';
    protected $data = [];

    public function __construct( string $template = null, string $path = null, $extension = null )
    {
        $loader = new Loader( $template, $path, $extension );
        $loader->parse();
        $this->content = $loader->getContent();
    }

    public function setData( array $data )
    {
        foreach( $data as $key => $content )
        {
            $this->data[$key]  = $content;
        }
    }

    public function parseIf()
    {

    }
}