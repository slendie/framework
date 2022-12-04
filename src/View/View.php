<?php

namespace Slendie\Framework\View;

use Slendie\Framework\View\Loader;
use Slendie\Framework\View\Transpiler;

class View
{
    protected $content = '';
    protected $cache;
    protected $data = [];

    public function __construct( string $template = null, string $path = null, $extension = null, string $cache_path = null )
    {
        $this->load( $template, $path, $extension );
        $this->transpile();

        $this->cache = new Cache( $this->content, $template, $cache_path, $extension );
    }

    public function load( string $template, string $path = null, $extension = null )
    {
        if ( empty($template) ) {
            $this->content = '';
        } else {
            $loader = new Loader( $template, $path, $extension );
            $loader->parse();
            $loader->cleanup();
            $this->setContent( $loader->getContent() );
        }
    }

    public function setContent( $content )
    {
        $this->content = $content;
    }

    public function getContent()
    {
        return $this->content;
    }

    public function transpile()
    {
        $transpiler = new Transpiler( $this->content );
        $transpiler->parse();
        $this->setContent( $transpiler->getContent() );
    }

    public function setData( array $data )
    {
        foreach( $data as $key => $content )
        {
            $this->setKey( $key, $content );
        }
    }

    public function setKey( $key, $content )
    {
        $this->data[$key] = $content;
    }

    public function render()
    {
        $this->cache->checkCache();

        ob_start();
        if ( count( $this->data ) > 0 ) {
            extract( $this->data );
        }
        include $this->cache->getCacheFilename();
        $content = ob_get_clean();

        return $content;
    }
}