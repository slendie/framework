<?php
namespace Slendie\Framework\Session;

class Session
{
    public static function get( $attribute )
    {
        if ( array_key_exists( $attribute, $_SESSION ) ) {
            return $_SESSION[ $attribute ];
        }
        return false;
    }

    public static function set( $attribute, $value )
    {
        $_SESSION[ $attribute ] = $value;
    }

    public static function has( $attribute ) 
    {
        return array_key_exists( $attribute, $_SESSION );
    }

    public static function flash( $attribute )
    {
        if ( !array_key_exists( $attribute, $_SESSION ) ) {
            return '';
        }
        $value = $_SESSION[ $attribute ];
        unset($_SESSION[ $attribute ]);
        return $value;
    }

    public static function setArrayItem( $attribute, $value, $index = null )
    {
        if ( !array_key_exists( $attribute, $_SESSION ) ) {
            $_SESSION[ $attribute ] = [];
        }
        if ( is_null( $index ) ) {
            $_SESSION[ $attribute ][] = $value;
        } else {
            $_SESSION[ $attribute][ $index ] = $value;
        }
    }

    public static function getArrayItem( $attribute, $index = null)
    {
        if ( !array_key_exists( $attribute, $_SESSION ) ) {
            return null;
        }
        if ( is_null( $index ) ) {
            $index = count( $_SESSION[$attribute] ) - 1;
        }
        if ( !array_key_exists( $index, $_SESSION[ $attribute ] )) {
            return null;
        }
        return $_SESSION[ $attribute ][ $index ];
    }

    public static function hasArrayItem( $attribute, $index ) 
    {
        if ( !array_key_exists( $attribute, $_SESSION ) ) {
            return false;
        }
        return array_key_exists( $index, $_SESSION[ $attribute ] );
    }

    public static function flushArrayItem( $attribute, $index )
    {
        if ( array_key_exists( $attribute, $_SESSION ) ) {
            if ( array_key_exists( $index, $_SESSION[ $attribute ]) ) {
                unset( $_SESSION[ $attribute ][ $index ] );
            }
        }
    }

    public static function flush( $attribute )
    {
        unset( $_SESSION[ $attribute ] );
    }
}