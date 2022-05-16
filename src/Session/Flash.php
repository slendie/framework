<?php
namespace Slendie\Framework\Session;

use Slendie\Framework\Routing\Request;

class Flash
{
    public static function error( $message )
    {
        Session::setArrayItem( 'errors', $message );
    }

    public static function errors()
    {
        $errors = Session::get('errors');
        unset( $_SESSION['errors']);
        return $errors;
    }

    public static function toast( $level, $message )
    {
        Session::setArrayItem('toast', ['level' => $level, 'message' => $message]);
    }

    public static function danger( $message )
    {
        self::toast('danger', $message);
    }

    public static function info( $message )
    {
        self::toast('info', $message);
    }

    public static function success( $message )
    {
        self::toast('success', $message);
    }

    public static function toasts()
    {
        return Session::flash('toast');
    }

    public static function isThereAnyError()
    {
        if ( array_key_exists( 'errors', $_SESSION ) ) {
            return true;
        }
        return false;
    }

    public static function setFieldError( $field, $message )
    {
        Session::setArrayItem('field_errors', $message, $field);
    }

    public static function hasError( $field )
    {
        if ( !array_key_exists('field_errors', $_SESSION) ) {
            return false;
        }
        if ( !array_key_exists( $field, $_SESSION['field_errors'] ) ) {
            return false;
        }
        return true;
    }

    public static function getFieldError( $field )
    {
        if ( !self::hasError( $field) ) {
            return null;
        }

        $message = Session::getArrayItem('field_errors', $field);
        unset( $_SESSION['field_errors'][ $field] );
        return $message;
    }

    public static function setOld( $field, $value )
    {
        Session::setArrayItem('old', $value, $field);
    }

    public static function getOld( $field, $default = null )
    {
        $value = Session::getArrayItem('old', $field);
        if ( is_null( $value ) ) {
            return $default;
        }
        return $value;
    }

    public static function hasOld( $field ) 
    {
        return Session::hasArrayItem( 'old', $field );
    }

    public static function old( $field, $default = null )
    {
        $request = Request::getInstance();

        if ( isset( $request->{$field} ) ) {
            $value = $request->{$field};
        } else {
            if ( self::hasOld( $field ) ) {
                $value = self::getOld( $field );
            } else {
                $value = $default;
            }
        }
        if ( is_null( $value ) ) {
            return '';
        } else {
            return $value;
        }
    }

    public static function flash( $message, $key = null )
    {
        Session::setArrayItem('flash', $message, $key);
    }

    public static function get( $key = null )
    {
        $value = Session::getArrayItem( 'flash', $key );
        Session::flushArrayItem( 'flash', $key );
        return $value;
    }

    public static function flush()
    {
        Session::flush( 'errors' );
        Session::flush( 'old' );
        Session::flush( 'flash' );
    }
}