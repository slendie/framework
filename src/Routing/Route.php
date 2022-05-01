<?php
namespace Slendie\Framework\Routing;

use Slendie\Framework\Routing\Router;

class Route
{
    protected static $router = NULL;

    private function __construct()
    {
    }

    protected static function getRouter()
    {
        if ( is_null(self::$router) )  {
            self::$router = new Router();
        }
        return self::$router;
    }

    public static function post( $pattern, $callback ) 
    {
        return self::getRouter()->post( $pattern, $callback );
    }
    public static function get( $pattern, $callback ) 
    {
        return self::getRouter()->get( $pattern, $callback );
    }
    public static function put( $pattern, $callback ) 
    {
        return self::getRouter()->put( $pattern, $callback );
    }
    public static function delete( $pattern, $callback ) 
    {
        return self::getRouter()->delete( $pattern, $callback );
    }

    public static function resolve( $pattern ) 
    {
        return self::getRouter()->resolve( $pattern );
    }
    public static function translate( $pattern, $params ) 
    {
        return self::getRouter()->translate( $pattern, $params );
    }
    public static function base()
    {
        return self::getRouter()->base();
    }
    public static function asset( $resource )
    {
        return self::getRouter()->asset( $resource );
    }
}