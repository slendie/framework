<?php
namespace Slendie\Framework\Routing;

class Router
{
    /**
     * Collection of routes
     */
    protected $route_collection;

    /**
     * Dispatcher handle route requests
     */
    protected $dispatcher;

    public function __construct() 
    {
        $this->route_collection = new RouteCollection();
        $this->dispatcher = new Dispatcher();
    }
    
    /**
     * Save a get route in its own collection.
     */
    public function get( $pattern, $callback ) 
    {
        $this->route_collection->add('get', $pattern, $callback);
        return $this;
    }

    /**
     * Save a post route in its own collection.
     */
    public function post( $pattern, $callback ) 
    {
        $this->route_collection->add('post', $pattern, $callback);
        return $this;
    }

    /**
     * Save a put route in its own collection.
     */
    public function put( $pattern, $callback ) 
    {
        $this->route_collection->add('put', $pattern, $callback);
        return $this;
    }

    /**
     * Save delete route in its own collection.
     */
    public function delete( $pattern, $callback ) 
    {
        $this->route_collection->add('delete', $pattern, $callback);
        return $this;
    }

    /**
     * Find a request and method in its own collection and return it.
     */
    public function find( $request_method, $pattern ) 
    {
        return $this->route_collection->where( $request_method, $pattern );
    }

    /**
     * Este método trata de executar o callback para a rota encontrada.
     * Vai utilizar-se do seu atributo dispatcher.
     */
    public function dispatch( $route, $request, $params, $namespace = "App\\Http\\Controllers\\" ) 
    {
        return $this->dispatcher->dispatch( $route->callback, $request, $params, $namespace );
    }

    /**
     * Return a not found page (HTTP 404)
     */
    protected function notFound() 
    {
        return header("HTTP/1.0 404 Not Found", true, 404);
    }

    /**
     * Este método é utilizado para resolver o request atual.
     * Procura nas rotas guardadas na coleção, uma com mesmo método e uri. [$this->find()]
     * Caso encontre, então executa o dispatch para executar o callback.
     */
    public function resolve( $request ) 
    {
        $method = $request->method();
        $uri = $request->uri();
        $route = $this->find( $request->method(), $request->uri() );

        if ( $route ) {
            $params = $route->callback['values'] ? $this->getValues( $request->uri(), $route->callback['values'] ) : [];
            return $this->dispatch( $route, $request, $params );
        }
        return $this->notFound();
    }

    protected function getValues( $pattern, $positions ) 
    {
        $result = [];

        $pattern = array_filter(explode('/', $pattern));

        dc('Router::getValues', $pattern);

        foreach( $pattern as $key => $value ) {
            if ( in_array($key - 1, $positions) ) {
                $result[array_search($key, $positions)] = $value;
            }
        }

        return $result;
    }

    /**
     * Translate a named route + params to an URL
     * Este método traduz uma rota (nome) em uma URL.
     */
    public function translate( $name, $params ) 
    {
        // As rotas retornadas através do isThereAnyHow são relativas.
        // A seguir é necessário criar as rotas absolutas.
        $pattern = $this->route_collection->isThereAnyHow( $name );

        if ( $pattern ) {
            $request = new Request();
            $protocol = $request->protocol();
            $server = $request->server();
            $uri = [];

            foreach( array_filter(explode('/', $request->uri())) as $key => $value ) {
                if ( $value == 'public' ) {
                    $uri[] = $value;
                    break;
                }
                $uri[] = $value;
            }
            $uri = implode('/', array_filter($uri)) . '/';

            dc('Router::translate', $name, $uri);

            // $response = $this->route_collection->convert( $pattern, $params );

            return $protocol . '://'. $server . $this->route_collection->convert( $pattern, $params );
        }
        return false;
    }

    public function base()
    {
        $request = new Request();
        $protocol = $request->protocol();
        $server = $request->server();

        return $protocol . '://' . $server;
    }

    public function asset( $resource )
    {
        return $this->base() . $resource;
    }
}