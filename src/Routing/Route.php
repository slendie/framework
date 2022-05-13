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
     * Save a post request in its collection
     */
    public static function post( $pattern, $callback ) 
    {
        return self::getRouter()->post( $pattern, $callback );
    }

    /**
     * Save a get request in its collection
     */
    public static function get( $pattern, $callback ) 
    {
        return self::getRouter()->get( $pattern, $callback );
    }

    /**
     * Save a put request in its collection
     */
    public static function put( $pattern, $callback ) 
    {
        return self::getRouter()->put( $pattern, $callback );
    }

    /**
     * Save a delete request in its collection
     */
    public static function delete( $pattern, $callback ) 
    {
        return self::getRouter()->delete( $pattern, $callback );
    }

    /**
     * Este método geralmente é chamado na App, para resolver o request atual.
     * Por sua vez, chama a classe Router para resolver.
     */
    public static function resolve( $request ) 
    {
        return self::getRouter()->resolve( $request );
    }

    /**
     * Translate a route into a request
     * Este método é chamado pelo helper, para construir as rotas nos templates
     * através da notação @route()
     * Ele chama o mesmo método do seu Router.
     */
    public static function translate( $pattern, $params ) 
    {
        return self::getRouter()->translate( $pattern, $params );
    }

    /**
     * Return site base URL
     */
    public static function base()
    {
        return self::getRouter()->base();
    }

    /**
     * Return a resource URL from base
     */
    public static function asset( $resource )
    {
        return self::getRouter()->asset( $resource );
    }
}