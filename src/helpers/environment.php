<?php
use Slendie\Framework\Environment\Env;

if ( !function_exists('env') ) {
    function env( $parameter ) {
        return Env::get( $parameter );
    }
} else  {
    die('Function env exists.');
}
