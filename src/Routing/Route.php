<?php
namespace Slendie\Framework\Routing;

/**
 * This is a singleton class that represent a route.
 */
class Route
{
    const NAMESPACE = "App\\Http\\Controllers\\";
    const WORD = "[A-Za-z0-9\-]{1,}";

    protected $method = null;
    protected $route = null;
    protected $pattern = null;
    protected $callback = null;
    protected $name = null;
    protected $params = [];
    protected $namespace = null;
    protected $middlewares = [];

    /**
     * Define its method, pattern, callback and optionally a namespace.
     */
    public function __construct( string $method, string $pattern, $callback, $namespace = null ) 
    {
        $this->method = $method;
        $this->route = $pattern;
        $this->pattern = self::parseUri( $pattern );
        $this->callback = $callback;
        if ( is_null($namespace) ) {
            $this->namespace = Route::NAMESPACE;
        } else {
            $this->namespace = $namespace;
        }
    }

    /**
     * Parse a defined route (with keys) into a pattern route (with regular expression).
     */
    public static function parseUri( $pattern )
    {
        $pattern = str_replace('/', '\/', $pattern);
        $pattern = str_replace('(', '\(', $pattern);
        $pattern = str_replace(')', '\)', $pattern);

        $variable_pattern = "/\{" . Route::WORD . "\}/";
        $catch_pattern = '(' . Route::WORD . ')';

        return  '/^' . preg_replace( $variable_pattern, $catch_pattern, $pattern ) . '$/';
    }

    /**
     * Retrieve route method.
     */
    public function method()
    {
        return $this->method;
    }

    /**
     * Retrieve route original route (with keys).
     */
    public function route()
    {
        return $this->route;
    }

    /**
     * Retrieve route pattern (with regular expression).
     */
    public function pattern()
    {
        return $this->pattern;
    }

    /**
     * Retrieve route callback.
     */
    public function callback()
    {
        return $this->callback;
    }

    /**
     * Retrieve route namespace.
     */
    public function namespace()
    {
        return $this->namespace;
    }

    /**
     * Retrieve route name.
     */
    public function name()
    {
        return $this->name;
    }

    /**
     * Define route name.
     */
    public function setName( $name )
    {
        $this->name = $name;
    }

    /**
     * Retrieve route params.
     */
    public function params()
    {
        return $this->params;
    }

    /**
     * Define route params.
     */
    public function setParams( $params )
    {
        $this->params = $params;
    }

    public function middlewares()
    {
        return $this->middlewares;
    }
    
    public function setMiddleware( $middleware )
    {
        $this->middlewares[] = $middleware;
    }

    /**
     * Translate route in an URL.
     */
    public function translate()
    {
        $key_pattern = '/\{(' . Route::WORD . ')\}/';
        preg_match_all( $key_pattern, $this->route, $matches );

        $pattern = $this->route;

        if ( count( $matches[0] ) > 0 ) {
            foreach( $matches[0] as $i => $key ) {
                $pattern = str_replace( $key, $this->params[ $matches[1][$i] ], $pattern);
            }
        }
        return Request::asset( $pattern );
    }

    /**
     * Dispatch this route.
     */
    public function dispatch()
    {
        $this->extractParams( Request::uri() );
        return Dispatcher::dispatch( $this );
    }

    /**
     * Extract params from route uri.
     */
    public function extractParams( $uri )
    {
        preg_match_all( $this->pattern, $uri, $matches);

        $key_pattern = '/\{(' . Route::WORD . ')\}/';

        if ( count( $matches[0] ) > 0 ) {
            preg_match_all( $key_pattern, $this->route, $key_matches);
        }

        $params = [];
        foreach( $key_matches[1] as $i => $key_name ) {
            $params[ $key_name ] = $matches[1][ $i ];
        }
 
        $this->setParams( $params );
    }
}