<?php
namespace Slendie\Framework\Routing;

/**
 * Uma classe estática que guarda as rotas do sistema.
 */
class RouteCollection
{
    private static $routes = array();
    private static $names = array();

    private function __construct() {}

    private function __close() {}

    /**
     * Add a route to the collection and return it.
     */
    public static function add( Route $route )
    {
        if ( !in_array( $route->method(), ['get', 'post'] ) ) {
            throw new Exception('Método ' . $route->method() . ' não implementado.');
        }

        self::update( $route );

        if ( !empty( $route->name() ) ) {
            self::addName( $route->method(), $route->pattern(), $route->name() );
        }
    }

    /**
     * Add route name and an index to pattern and method.
     */
    public static function addName( $method, $pattern, $name ) 
    {
        self::$names[ $name ] = [ 'pattern' => $pattern, 'method' => $method ];
    }

    /**
     * Retrieve route by index: $method and $pattern.
     */
    public static function getByIndex( $method, $pattern ) 
    {
        if ( array_key_exists( $pattern, self::$routes ) ) {
            $route_pattern = self::$routes[ $pattern ];

            if ( array_key_exists( $method, $route_pattern ) ) {
                return $route_pattern[ $method ];
            }
        }
        return false;
    }

    /**
     * Find a route in the collection and return it if found.
     * Otherwise, return false.
     */
    public static function find( string $method, string $pattern )
    {
        foreach( self::$routes as $key => $route_pattern ) {
            if ( preg_match( $key, $pattern, $matches) ) {
                return self::getByIndex( $method, $key );
            }
        }
        throw new \Exception("Method {$method} cannot be found with {$pattern}.");
    }

    /**
     * Find (and retrieve) a route by name.
     */
    public static function findByName( string $name ) 
    {
        if ( array_key_exists( $name, self::$names ) ) {
            $index = self::$names[ $name ];

            return self::getByIndex( $index['method'], $index['pattern'] );
        }
        throw new \Exception("Route {$name} cannot be found.");
    }

    /**
     * Set a name to a route.
     */
    public static function setName( string $method, string $pattern, string $name ) 
    {
        $route = self::$routes[ $pattern ][ $method ];

        if ( $route ) {
            $route->name( $name );
            self::update( $route );
            self::addName( $method, $pattern, $name );
            return true;
        } else {
            throw new \Exception("Route {$pattern} with method {$method} not defined.");
        }
    }

    public static function update( Route $route )
    {
        // Check if route is already defined or not.
        if ( !array_key_exists( $route->pattern() , self::$routes ) ) {
            self::$routes[ $route->pattern() ] = [];
        }

        // Locate route in the collection
        self::$routes[ $route->pattern() ][ $route->method() ] = $route;
    }
}