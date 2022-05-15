<?php
namespace Slendie\Framework\Routing;

/**
 * Class responsible for dispatch
 */
class Dispatcher
{
    /**
     * Dispatch a route
     */
    public static function dispatch( Route $route )
    {
        $last_index = -1;
        $middlewares = $route->middlewares();

        // Run middleware before
        foreach( $middlewares as $i => $middleware ) {
            $last_index = $i;
            $callback = MiddlewareCollection::get( $middleware );

            if ( is_callable( $callback ) ) {
                $continue = call_user_func( $callback );
            } else {
                $continue = call_user_func( array( $callback, 'up' ) );
            }
            if ( !$continue ) {
                exit;
            }
        }

        try {
            if (is_callable( $route->callback() )) {
                $response = call_user_func_array( $route->callback(), array_values( $route->params() ));
            } else {
                $call = explode("@", $route->callback() );

                if (count($call) == 2) {
                    $controller = $route->namespace() . $call[0];
                    $controller = new $controller;
                    $method     = $call[1];
                    $response = call_user_func_array(array($controller, $method), array_values( $route->params() ));
                } else {
                    throw new Exception("Declaração de rota incorreta");
                }
            }
        } catch (\Exception $e) {
            echo $e->getMessage();
        }

        // Run middleware after
        for( $i = $last_index; $i >= 0; $i-- ) {
            $callback = MiddlewareCollection::get( $middlewares[ $i ] );
            if ( is_callable( $callback ) ) {
                $continue = call_user_func( $callback );
            } else {
                $continue = call_user_func( array( $callback, 'down' ) );
            }
            if ( !$continue ) {
                exit;
            }
        }

        return $response;
    }
}