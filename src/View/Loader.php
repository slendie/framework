<?php

namespace Slendie\Framework\View;

class Loader
{
    /**
     * @param string $path
     */
    protected $path;

    /**
     * @param string $file
     */
    protected $file;

    /**
     * @param string $extension
     */
    protected $extension;

    /**
     * @param string $path
     * @param string $extension
     * @throws \Exception
     */
    public function construct( string $path = null, $extension = null )
    {
        if ( is_null( $path ) ) {
            $path = SITE_FOLDER . '/resources/views/';
        }
        if ( is_null( $extension ) ) {
            echo "Loader::construct() - extension is null";
            $extension = '.tpl.php';
        }

        $this->setBasePath( $path );
        $this->extension = $extension;
    }

    public function setBasePath( string $path = null )
    {
        if ( empty( $path ) ) {
            $path = SITE_FOLDER . '/resources/views/';
        }

        $path = $this->convertToPath( $path );

        if ( ! $path || ! \is_dir( $path ) ) {
            throw new \Exception( "View path [{$path}] not found." );
        }

        $this->path = $path;
    }

    public function getBasePath()
    {
        return $this->path;
    }
    public function getExtension()
    {
        return $this->extension;
    }

    private function convertToPath( string $path )
    {
        $converted_path = str_replace('.', DIRECTORY_SEPARATOR, $path);
        $converted_path = str_replace('\\', DIRECTORY_SEPARATOR, $converted_path);
        $converted_path = str_replace('/', DIRECTORY_SEPARATOR, $converted_path);

        return $converted_path;
    }

    private function check( $file )
    {
        if ( empty( $file ) ) {
            return false;
        }

        if ( !file_exists( $file ) ) {
            throw new \Exception( 'File not found: ' . $file );
        }

        if ( !is_readable( $file) ) {
            throw new \Exception( 'File not readable: ' . $file );
        }

        return true;
    }

    private function read( $file )
    {
        if ( !$this->check( $file ) ) {
            return '';
        }

        $content = file_get_contents( $file );
    }
}