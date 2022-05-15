<?php
namespace Slendie\Framework\Routing;

/**
 * Router class: control all routes (sorry for the redundancy).
 */
class Router
{
    // This instance
    protected static $instance = null;

    // Latest route
    protected static $route = null;

    private function __construct() {}

    // Singleton
    public static function getInstance() 
    {
        if ( is_null( self::$instance ) ) {
            self::$instance = new Router();
        }
        return self::$instance;
    }

    /**
     * Add a route to the route collection.
     */
    public static function add( string $method, string $pattern, $callback )
    {
        self::$route = new Route( $method, $pattern, $callback );

        RouteCollection::add( self::$route );

        // Return this instance
        return self::getInstance();
    }

    /**
     * Add a route with get method.
     */
    public static function get( string $pattern, $callback )
    {
        return self::add( 'get', $pattern, $callback);
    }

    /**
     * Add a route with post method.
     */
    public static function post( string $pattern, $callback )
    {
        return self::add( 'post', $pattern, $callback);
    }

    /**
     * Add a route with put method.
     */
    public static function put( string $pattern, $callback )
    {
        return self::add( 'put', $pattern, $callback);
    }

    /** 
     * Add a route with delete method.
     */
    public static function delete( string $pattern, $callback )
    {
        return self::add( 'delete', $pattern, $callback);
    }

    /**
     * Define a name to the current route (and update on the route collection).
     */
    public static function name( string $name )
    {
        self::$route->setName( $name );
        RouteCollection::setName( self::$route->method(), self::$route->pattern(), self::$route->name() );
    }


    /**
     * Resolve current request to a route callback.
     */
    public static function resolve()
    {
        $request = Request::getInstance();

        self::$route = RouteCollection::find( $request->method(), $request->uri() );

        if ( self::$route ) {
            return self::$route->dispatch();
        } else {
            return self::notFound();
        }
    }

    /**
     * Send a not found response (HTTP/404)
     */
    protected static function notFound() 
    {
        return header("HTTP/1.0 404 Not Found", true, 404);
    }

    /**
     * Translate a route name + params into a url.
     */
    public static function translate( string $name, $params = [] )
    {
        if ( is_null( $params ) ) {
            $params = [];
        }
        self::$route = RouteCollection::findByName( $name );

        if ( self::$route ) {
            self::$route->setParams( $params );
            return self::$route->translate();
        }
        
        return false;
    }
}