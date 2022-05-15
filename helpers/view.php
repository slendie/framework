<?php

if ( !function_exists('view') ) {
    function view( $template, $data = [] ) 
    {
        return (new View())->render( $template, $data )
    }
}