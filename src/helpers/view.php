<?php

use Slendie\Framework\View\View;

if ( !function_exists('view') ) {
    function view( $template_file, $data = [] )
    {
        $view = new View( $template_file );
        $view->setData( $data );
        return $view->render();
    }
}