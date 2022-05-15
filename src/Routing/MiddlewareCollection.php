<?php
namespace Slendie\Framework\Routing;

class MiddlewareCollection
{
    protected static $middlewares = [];

    public static function register(string $name, $callback)
    {
        self::$middlewares[ $name ] = $callback;
    }

    public static function get( string $name )
    {
        return self::$middlewares[ $name ];
    }
}