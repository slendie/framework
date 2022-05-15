<?php
use Slendie\Framework\Routing\Router;
use Slendie\Framework\Routing\Request;

if ( !function_exists('request') ) {
    function request( $key = NULL ) 
    {
        $request = Request::getInstance();
        return !is_null($key) ? $request->get($key) : $request;
    }    
}

if ( !function_exists('resolve') ) {
    function resolve()
    {
        return Router::resolve();
    }
}

if ( !function_exists('route') ) {
    function route( $name, $params = NULL )
    {
        return Router::translate( $name, $params );
    }
}

if ( !function_exists('redirect') ) {
    function redirect( $route, $params = NULL )
    {
        header('Location: ' . route($route, $params));
        exit();
    }    
}

if ( !function_exists('back') ) {
    function back()
    {
        header('Location: ' . $_SERVER['HTTP_REFERER']);
        exit();
    }    
}

if ( !function_exists('base') ) {
    function base()
    {
        return Request::base();
    }    
}

if ( !function_exists('asset') ) {
    function asset( $resource = "" )
    {
        return Request::asset( $resource );
    }
}

if ( !function_exists('http') ) {
    function http( $code ) {
        switch( $code ) {
            case '403':
                Router::forbidden();
                break;

            case '404':
                Router::notFound();
                break;

            default;
                throw new \Exception('Código HTTP ' . $code . ' não definido.');
                break;

        }
    }
}