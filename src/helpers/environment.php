<?php
use Slendie\Framework\Environment\Environment;

if ( !function_exists('env') ) {
    function env( $parameter ) {
        $env = Environment::getInstance();
        $env->load();
        return $env->get( $parameter );
    }
} else  {
    die('Function env already exists.');
}
