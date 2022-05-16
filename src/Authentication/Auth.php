<?php
namespace Slendie\Framework\Authentication;

use App\Models\User;

class Auth
{
    protected static $instance = null;

    private function __construct() {}

    public function getInstance()
    {
        if ( is_null( self::$instance ) ) {
            self::$instance = new Auth();
        }
        return self::$instance;
    }

    public static function isLoggedIn()
    {
        $id = self::user_id();
        if ( $id === false ) {
            return false;
        }
        return true;
    }

    public static function user_id()
    {
        return session('user-id');
    }

    public static function user()
    {
        $id = self::user_id();

        if ( $id ) {
            return User::find( $id );
        }

        return false;
    }

    public static function authenticate( $email, $password )
    {
        $user = (new User())->where('email', $email)->get();

        if ( $user ) {
            if ( password_verify( $password, $user->password ) ) {
                self::setUser( $user );
                return true;
            }
        }

        return false;
    }

    private static function setUser( $user )
    {
        Session::set('user-id', $user->id );
        Session::set('user-name', $user->name );
    }
}