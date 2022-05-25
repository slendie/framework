<?php

use Slendie\Framework\Session\Session;
use Slendie\Framework\Session\Flash;

if ( !function_exists('session') ) {
    function session( $attribute ) 
    {
        return Session::get( $attribute );
    }
}

if ( !function_exists('set_session') ) {
    function set_session( $attribute, $value ) 
    {
        return Session::set( $attribute, $value );
    }
}

if ( !function_exists('has_errors') ) {
    function has_errors() 
    {
        return Flash::isThereAnyError();
    }
}

if ( !function_exists('has_error') ) {
    function has_error( $field ) 
    {
        return Flash::hasError( $field );
    }
}

if ( !function_exists('error') ) {
    function error( $field ) 
    {
        if ( Flash::hasError( $field ) ) {
            return Flash::getFieldError( $field );
        } else {
            return '';
        }
    }
}

if ( !function_exists('errors') ) {
    function errors()
    {
        return Flash::errors();
    }
}

if ( !function_exists('old') ) {
    function old( $field, $default = null) 
    {
        echo Flash::old( $field, $default );
    }
}

if ( !function_exists('has_toasts') ) {
    function has_toasts()
    {
        return Session::has('toast');
    }
}

if ( !function_exists('toasts') ) {
    function toasts()
    {
        if ( has_toasts() ) {
            return Flash::toasts();
        } else {
            return [];
        }        
    }
}