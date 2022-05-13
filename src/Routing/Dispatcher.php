<?php
namespace Slendie\Framework\Routing;

/**
 * Esta classe serve apenas para executar um callback.
 * Ele recebe um callback, o request atual, opcionalmente os parâmetros para o callback e opcionalmente o namespace.
 * Quando chama a função, passa apenas o request junto com os valores dos parâmetros passados.
 */
class Dispatcher
{
    public function dispatch( $callback, $request, $params = [], $namespace = "App\\Http\\Controllers\\" ) 
    {
        if ( is_callable( $callback['callback'] )) {
            return call_user_func_array( $callback['callback'], array_values( array_merge(['request' => $request], $params) ));

        } elseif ( is_string( $callback['callback'] )) {
            if ( false !== !!strpos( $callback['callback'], '@') ) {

                if ( !empty($callback['namespace']) ) {
                    $namespace = $callback['namespace'];
                }

                $callback['callback'] = explode('@', $callback['callback']);
                $controller = $namespace.$callback['callback'][0];
                $action = $callback['callback'][1];

                $rc = new \ReflectionClass($controller);

                if ( $rc->isInstantiable() && $rc->hasMethod( $action )) {
                    return call_user_func_array( array( new $controller, $action ), array_values( array_merge(['request' => $request], $params) ) );
                } else {
                    throw new \Exception('Erro no dispatcher: controller não pôde ser instanciado ou método não existe');
                }
            }
        }
        throw new \Exception('Erro no dispatcher: método não implementado.');
    }
}