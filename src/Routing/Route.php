<?php
namespace Slendie\Framework\Routing;

use Slendie\Framework\Routing\Router;

/**
 * This class handle with routes:
 * - Hold routes defined by the App
 * 
 * - Find proper route from URL and HTTP method
 * - Return an action when found this route
 * - Return false when route was not found
 */
class Route
{
    // Router instance.
    protected static $router = NULL;

    // Singleton
    private function __construct() {}

    // Singleton. Same as getInstance(), but this instance is not a Route instance, but Router.
    protected static function getRouter()
    {
        if ( is_null(self::$router) )  {
            self::$router = new Router();
        }
        return self::$router;
    }

    /**
     * Hold post call on instance 
     */
    public static function post( $pattern, $callback ) 
    {
        return self::getRouter()->post( $pattern, $callback );
    }

    /**
     * Hold get call on instance
     */
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
    public static function resolve( $request ) 
    {
        return self::getRouter()->resolve( $request );
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