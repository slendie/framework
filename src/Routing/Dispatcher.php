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
        try {
            if (is_callable( $route->callback() )) {
                return call_user_func_array( $route->callback(), array_values( $route->params() ));
            } else {
                $call = explode("@", $route->callback() );

                if (count($call) == 2) {
                    $controller = $route->namespace() . $call[0];
                    $controller = new $controller;
                    $method     = $call[1];
                    return call_user_func_array(array($controller, $method), array_values( $route->params() ));
                } else {
                    throw new Exception("Declaração de rota incorreta");
                }
            }
        } catch (\Exception $e) {
            echo $e->getMessage();
        }
    }
}