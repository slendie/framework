<?php
use Slendie\Framework\Environment\Environment;

if ( !function_exists('env') ) {
    function env( $parameter ) {
        return Environment::get( $parameter );
    }
} else  {
    die('Function env exists.');
}
