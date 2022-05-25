<?php
namespace Slendie\Framework\Environment;

class Env
{
    protected static $instance = null;
    protected static $env = '.env';
    protected static $original_env = '.env';
    protected static $data = [];
    protected static $loaded = false;
    protected static $root = null;

    private function __construct() {}

    public static function getInstance()
    {
        if ( is_null( self::$instance ) ) {
            self::$instance = new Env();
            self::load();
        }
        return self::$instance;
    }

    public static function setFile( $env )
    {
        self::$env = $env;
        self::load();
    }

    public static function setOriginalFile( $env )
    {
        self::$original_env = $env;
    }

    public static function getFile()
    {
        return self::$env;
    }

    private static function setRootPath()
    {
        $dir = explode( DIRECTORY_SEPARATOR, __DIR__ );
        if ( count( $dir ) > 5 ) {
            $path = implode( DIRECTORY_SEPARATOR, array_slice( $dir, 0, -5 )) . DIRECTORY_SEPARATOR;
        } else {
            throw \Exception(sprint('Cannot determine base path.'));
        }
        self::$root = $path;
    }

    public static function get( $attr )
    {
        if ( !self::$loaded ) {
            var_dump( debug_print_backtrace() );
            throw new \Exception('Env file is not loaded.');
        }

        if ( array_key_exists( $attr,  self::$data ) ) {
            return self::$data[ $attr ];
        }
        foreach( self::$data as $section ) {
            if ( array_key_exists( $attr, $section ) ) {
                return $section[ $attr ];
            }
        }
        return null;
    }

    public function __get( $attr ) 
    {
        if ( !self::$loaded ) {
            throw new \Exception('Env file is not loaded.');
        }
        
        return self::get( $attr );
    }

    public static function load()
    {
        self::$data = [];
        self::setRootPath();

        $env = self::$root . self::$env;
        
        if ( !file_exists( $env ) ) {
            throw new \Exception('Env file ' . $env . ' does not exists.');
            return;
        }

        self::$data = parse_ini_file( $env, true );

        self::$loaded = true;
    }

    public static function getRoot()
    {
        return self::$root;
    }

    public static function mode( $mode )
    {
        if ( $mode == 'production' ) {
            $env_file = self::$original_env;
        } else {
            $env_file = self::$original_env . "." . $mode;
        }
        self::setFile( $env_file );
    }
}