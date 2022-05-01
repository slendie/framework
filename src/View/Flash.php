<?php
namespace Slendie\Framework\View;

class Flash
{
    public static function setMessage( $message, $level = 'success' )
    {
        if ( !isset( $_SESSION['flash']) ) {
            $_SESSION['flash'] = [];
        }
        if ( !isset($_SESSION['flash'][$level]) ) {
            $_SESION['flash'][$level] = [];
        }
        $_SESSION['flash'][$level][] = $message;
    }

    public static function success( $message ) 
    {
        self::setMessage( $message, 'success' );
    }
    public static function warning( $message )
    {
        self::setMessage( $message, 'warning' );
    }
    public static function info( $message )
    {
        self::setMessage( $message, 'info' );
    }
    public static function error( $message )
    {
        self::setMessage( $message, 'error');
    }

    public static function flash()
    {
        echo '<script>';
        // echo '$(function() {';
        if ( isset( $_SESSION['flash'] )) {
            $flash = $_SESSION['flash'];
            foreach( $flash as $level => $messages ) {
                switch ($level) {
                    case 'success':
                        foreach( $messages as $message ) {
                            echo "toastr.success('" . $message . "');";
                        }
                        break;

                    case 'warning':
                        foreach( $messages as $message ) {
                            echo "toastr.warning('" . $message . "');";
                        }
                        break;

                    case 'info':
                        foreach( $messages as $message ) {
                            echo "toastr.info('" . $message . "');";
                        }
                        break;

                    case 'error':
                        foreach( $messages as $message ) {
                            echo "toastr.error('" . $message . "');";
                        }
                        break;

                }
            }
        }
        // echo '});';
        echo '</script>';
        self::flush();
    }

    public static function flush()
    {
        unset( $_SESSION['flash'] );
    }
}