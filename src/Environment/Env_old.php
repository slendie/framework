<?php
namespace Slendie\Framework\Environment;

class Env
{
    protected static $instance = null;
    private static $env_file = "";
    private static $env = [];
    private static $base = "";

    /* Constructor */
    private function __construct() {}

    /* Magic methods */
    public function __get( $key )
    {
        return self::get( $key );
    }

    /* Get a key from current config */
    public static function get( $key )
    {
        self::checkInstance();
        
        if ( array_key_exists( $key, self::$env ) ) {
            return self::$env[ $key ];
        } else {
            foreach( self::$env as $section ) {
                if ( is_array( $section ) ) {
                    if ( array_key_exists( $key, $section) ) {
                        return $section[ $key ];
                    }
                }
            }
        }

        throw new \Exception('Environment key ' . $key . ' does not exists.');
    }

    public static function getInstance( $env_file = NULL )
    {
        self::checkInstance($env_file);

        return self::$instance;
    }

    private static function checkInstance( $env_file = NULL )
    {
        if ( is_null(self::$instance) ) {
            self::$instance = new Env();
            self::setEnv( $env_file );

            // Load data from file
            self::$instance->load();

            // Load aditional data
            self::loadSettings();
        }
    }

    public static function setEnv( $env_file = NULL )
    {
        if ( is_null( $env_file ) ) {
            self::setDefaultEnv();
            return;
        } else {
            self::$base = self::_getPath( $env_file );
            self::$env_file = str_replace( self::$base, '', $env_file );
        }
    }

    /* Set site folder and set default env file */
    public static function setDefaultEnv() {
        self::setBase( self::_getSiteFolder() );
        self::$env_file = '.env';
    }

    /* Get site folder path from system */
    private static function _getSiteFolder()
    {
        $dir = explode( DIRECTORY_SEPARATOR, __DIR__ );
        if ( count( $dir ) > 5 ) {
            $path = implode( DIRECTORY_SEPARATOR, array_slice( $dir, 0, -5 )) . DIRECTORY_SEPARATOR;
        } else {
            throw \Exception(sprint('Cannot determine base path.'));
        }
        return $path;
    }

    /* Set site folder path for this class */
    public static function setBase( $site_folder )
    {
        self::$base = $site_folder;
    }

    /* Get path for the $filePath */
    private static function _getPath( $filePath )
    {
        $filePath = str_replace('/', DIRECTORY_SEPARATOR, $filePath);
        $filePath = str_replace('\\', DIRECTORY_SEPARATOR, $filePath);
        $parts = explode( DIRECTORY_SEPARATOR, $filePath );
        $path = "";
        for( $i = 0; $i < count($parts); $i++ ) {
            if ( "" != $path ) {
                $path .= DIRECTORY_SEPARATOR;
            }
            $path .= $parts[$i];
        }
        $path .= DIRECTORY_SEPARATOR;

        return $path;
    }

    public static function load( $env_file = NULL )
    {
        self::_checkEnv( $env_file );

        $file = self::getEnvFile();
        if ( !file_exists($file) ) {
            throw new \Exception( sprintf('File %s does not exists.', $file) );
        }

        self::$env = parse_ini_file( $file, true );
    }

    /* Determine if $env_file exists or if we need to get from default */
    private static function _checkEnv( $env_file )
    {
        if ( !is_null( $env_file ) ) {
            self::setEnv( $env_file );
        } else {
            self::setDefaultEnv();
        }
    }

    /* Determine full path of env file */
    public static function getEnvFile()
    {
        return self::$base . self::$env_file;
    }

    public static function loadSettings()
    {
        // Load here other system default info
        self::$env['base_dir'] = self::getBase();
        self::$env['env_file'] = self::getEnvFile();
    }

    /* If base path not defined, then define based current file and return it */
    public static function getBase()
    {
        if ( "" == self::$base ) {
            self::$base = self::_getSiteFolder();
        }
        return self::$base;
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
        self::checkInstance();

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
}