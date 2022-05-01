<?php
use Slendie\Framework\Routing\Route;
use Slendie\Framework\Routing\Request;

function request() 
{
    return new Request();
}

function resolve( $request = NULL )
{
    if ( is_null($request) ) {
        $request = request();
    }
    return Route::resolve( $request );
}

function route( $name, $params = NULL )
{
    return Route::translate( $name, $params );
}

function redirect( $pattern, $params = NULL )
{
    header('Location: ' . route($pattern, $params));
    exit();
}

function back()
{
    header('Location: ' . $_SERVER['HTTP_REFERER']);
    exit();
}

function base()
{
    return Route::base();
}

function asset( $resource = "" )
{
    return Route::asset( $resource );
}