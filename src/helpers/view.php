<?php

use Slendie\Framework\View\Template;

if ( !function_exists('view') ) {
    function view( $template, $data = [] ) 
    {
        return (new Template())->render( $template, $data );
    }
}