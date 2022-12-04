<?php

namespace Slendie\Framework\View;

use Slendie\Framework\View\Loader;

class Cache
{
    protected $content = '';
    protected $view_file = '';
    protected $cache_path = '';
    protected $extension = '';

    public function __construct( string $content, string $view_file, string $cache_path = null, string $extension = null )
    {
        $this->setContent( $content );

        $this->view_file = Loader::convertToPath( $view_file );

        $this->setCachePath( $cache_path );

        if ( empty( $extension ) ) {
            $extension = env('VIEW')['VIEW_EXTENSION'];
        }
        $this->extension = $extension;

        $this->makeCachePath();
    }

    public function setCachePath( string $path = null )
    {
        if ( empty( $cache_path ) ) {
            $cache_path = SITE_FOLDER . env('VIEW')['VIEW_CACHE'];
        }
        $this->cache_path = Loader::convertToPath( $cache_path );

        if ( substr( $this->cache_path, -1 ) !== DIRECTORY_SEPARATOR ) {
            $this->cache_path .= DIRECTORY_SEPARATOR;
        }
    }

    public function setContent( string $content )
    {
        $this->content = $content;
    }

    public function getContent()
    {
        return $this->content;
    }

    public function checkCache()
    {
        $cache = $this->getCacheFile();

        if ( $this->content != $cache ) {
            $this->createCache();
        }

        return $this->getContent();
    }

    public function getCacheFilename()
    {
        return $this->cache_path . $this->view_file . '.' . $this->extension;
    }

    public function getCacheFile()
    {
        $cache_file = $this->getCacheFilename();

        if ( !file_exists( $cache_file ) ) {
            return '';
        }

        $cache = new Loader( $this->view_file, $this->cache_path, $this->extension );
        return $cache->getContent();
    }

    public function makeCachePath()
    {
        $cache_file = $this->getCacheFilename();

        $parts = explode(DIRECTORY_SEPARATOR, $cache_file );
        $file = array_pop( $parts );

        $dir = '';
        foreach( $parts as $part ) {
            $dir .= $part . DIRECTORY_SEPARATOR;
            if ( !is_dir( $dir ) ) {
                mkdir( $dir );
            }
        }
    }

    public function createCache()
    {
        $cache_file = $this->getCacheFilename();

        return file_put_contents( $cache_file, $this->content );
    }
}