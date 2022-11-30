<?php

namespace Slendie\Framework\Environment;

class Environment
{
    private static $instance = null;
    private static $env_file = SITE_FOLDER . '/.env';
    private static $env = [];

    private function __construct() {
    }

    public static function getInstance()
    {
        if ( is_null(self::$instance) ) {
            self::$instance = new Environment();
        }

        return self::$instance;
    }

    /**
     * Set the environment file name
     */
    public static function setEnvFile( $env_file )
    {
        if ( !file_exists( $env_file ) ) {
            throw new \Exception('Environment file ' . $env_file . ' does not exists.');
        }

        self::$env_file = $env_file;
    }

    /**
     * Get the environment file name
     */
    public function getFilename()
    {
        return self::$env_file;
    }

    public static function load( $force = false )
    {
        if ( !is_readable( self::$env_file ) ) {
            throw new \Exception('Environment file ' . self::$env_file . ' is not readable.');
        }

        $section = '';
        $lines = file( self::$env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
        foreach( $lines as $line ) {
            $line = trim( $line );

            /* Remove commented lines */
            if ( strpos( $line, '#' ) === 0 ) {
                continue;
            }

            if ( strpos( $line, ' # ' ) !== false ) {
                $line = substr( $line, 0, strpos( $line, ' # ' ) );
            }

            /* Check if is a section */
            $pattern = '/\[([\w]+)\]/';
            preg_match( $pattern, $line, $matches);

            if ( !is_null( $matches ) && count( $matches ) > 0 ) {
                $section = strtoupper( $matches[1] );
                continue;
            }

            /* Get parts */
            $parts = explode( '=', $line );
            if ( count( $parts ) == 0 ) {
                throw new \Exception('Environment file ' . self::$env_file . ' line ' . $line . ' is not valid.');
                die();
            }

            if ( count( $parts ) == 1 ) {
                $parts[1] = '';
            }
            $key = strtoupper( trim( $parts[0] ) );
            $value = trim( $parts[1] );

            /* Handle double quotes */
            $pattern = '/\"(.*)\"/';
            preg_match( $pattern, $value, $matches);

            if ( !is_null( $matches ) ) {
                if ( count( $matches ) > 0 ) {
                    $value = $matches[1];
                }
            }

            if ( empty( $section ) ) {
                self::$env[ $key ] = $value;
            } else {
                if ( !array_key_exists( $section, self::$env ) ) {
                    self::$env[ $section ] = [];
                }
                self::$env[ $section ][ $key ] = $value;
            }

            $composed_key = ( empty( $section ) ? $key : $section . '.' . $key );

            if ( $force || ( !array_key_exists($composed_key, $_SERVER) && !array_key_exists($composed_key, $_ENV) ) ) {
                putenv( $composed_key . '=' . $value );
                $_ENV[$composed_key] = $value;
                $_SERVER[$composed_key] = $value;
            }   
        }
    }

    public static function forceLoad()
    {
        self::load( true );
    }

    public function get( $key )
    {
        if ( array_key_exists( $key, self::$env ) ) {
            return self::$env[ $key ];
        } else {
            return NULL;
        }
    }

    public function all()
    {
        return self::$env;
    }
}