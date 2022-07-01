<?php
namespace Slendie\Framework\View;

class Template
{
    protected $loader;

    public function __construct()
    {
    }

    public function render( $template, $params = [] )
    {
        $this->loader = new Loader( $template, $params );
        $this->loader->render();
        return $this->loader->get();
    }
}