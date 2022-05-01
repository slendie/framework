<?php
namespace Slendie\Framework\Environment;

class Environment
{
    private static $instance = NULL;
    private static $base = "";
    private static $env_file = "";
    private static $env = [];

    private function __construct() {}

    public static function getInstance( $env_file = NULL )
    {
        if ( is_null(self::$instance) ) {
            self::$instance = new Environment();
            self::setEnv( $env_file );

            // Load data from file
            self::$instance->load();

            // Load aditional data
            self::loadSettings();
        }

        return self::$instance;
    }

    public static function setDefaultEnv() {
        $dir = explode( DIRECTORY_SEPARATOR, __DIR__ );
        if ( count( $dir ) > 5 ) {
            $path = implode( DIRECTORY_SEPARATOR, array_slice( $dir, 0, -5 )) . DIRECTORY_SEPARATOR;
        } else {
            throw \Exception(sprint('Cannot determine base path.'));
        }
        // $path = SITE_FOLDER;
        self::setEnv( $path . '.env ');
    }

    public static function setBase( $site_folder )
    {
        self::$base = $site_folder;
    }

    public static function setEnv( $env_file = NULL )
    {
        if ( is_null( $env_file ) ) {
            self::setDefaultEnv();
            return;
        }
        self::$env_file = $env_file;
    }

    public static function load( $env_file = NULL )
    {
        if ( !is_null( $env_file ) ) {
            self::setEnv( $env_file );
        } else {
            self::setDefaultEnv();
        }
        $file = self::$env_file;
        if ( !file_exists($file) ) {
            throw new \Exception(sprintf('File %s does not exists.', $file));
        }

        self::$env = parse_ini_file( $file, true );
    }

    public static function loadSettings()
    {
        if ( "" == self::$base ) {
            $dir = explode( DIRECTORY_SEPARATOR, __DIR__ );
            if ( count( $dir ) > 5 ) {
                $path = implode( DIRECTORY_SEPARATOR, array_slice( $dir, 0, -5 )) . DIRECTORY_SEPARATOR;
            } else {
                throw \Exception(sprint('Cannot determine base path.'));
            }
            // $path = SITE_FOLDER;
        } else {
            $path = self::$base;
        }

        // Load here other system default info
        self::$env['base_dir'] = $path;
        self::$env['env_file'] = self::$env_file;
    }

    public static function getSection( $section )
    {
        $section = self::get( $section );
        if ( is_array( $section ) ) {
            return $section;
        } else {
            return [];
        }
    }

    public static function getKey( $section, $key )
    {
        if ( array_key_exists( $section, self::$env ) ) {
            if ( array_key_exists( $key, self::$env[$section] ) ) {
                return self::$env[ $section ][ $key ];
            } else {
                return '';
            }
        } else {
            return '';
        }
        
    }

    public static function get( $key )
    {
        if ( array_key_exists( $key, self::$env ) ) {
            return self::$env[ $key ];
        } else {
            foreach( self::$env as $i => $group ) {
                if ( array_key_exists( $key, $group )) {
                    return $group[$key];
                }
            }
            return '';
        }
        
    }

    public function __get( $key )
    {
        return self::get( $key );
    }
}