<?php
namespace Slendie\Framework\Routing;

class Request
{
    protected $files;
    protected $domain;
    protected $base;
    protected $uri;
    protected $method;
    protected $protocol;
    protected $server;
    protected $data = [];
    protected $port;
    protected static $instance = null;

    private function __construct() {
        // Prevent access from CLI or CGI.
        if ( is_null( $_SERVER ) || !array_key_exists('SERVER_NAME', $_SERVER) ) {
            return;
        }

        $this->domain = $_SERVER['SERVER_NAME'];

        // Remove GET parameters
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        if ( preg_match('/\?.*/', $uri, $matches) ) {
            $uri = str_replace( $matches[0], '', $uri);
        }
        $this->uri = $uri;
        $this->method = strtolower($_SERVER['REQUEST_METHOD']);
        $this->protocol = isset( $_SERVER['HTTPS'] ) ? 'https' : 'http';
        $this->port = $_SERVER['SERVER_PORT'];

        if ( $this->port != 80 && !empty( $this->port ) ) {
            $this->server = $this->domain . ':' . $this->port . '/';
        } else {
            $this->server = $this->domain . '/';
        }
        $this->base = $this->protocol . '://' . $this->server;

        $this->setData();

        if ( count( $_FILES ) > 0 ) {
            $this->setFiles();
        }
    }

    public static function getInstance()
    {
        if ( is_null( self::$instance ) ) {
            self::$instance = new Request();
        }
        return self::$instance;
    }

    protected function setData() 
    {
        if ( !empty($_GET) ) {
            $this->data = $_GET;
        }
        switch ( $this->method ) {
            case 'post':
                $this->data = array_merge( $this->data, $_POST );
                break;

            case 'get':
                break;

            case 'head':
            case 'put':
            case 'delete':
            case 'options':
                $data = [];
                parse_str( file_get_contents('php://input'), $data );
                $this->data = array_merge( $this->data, $data );
                break;
        }
    }

    protected function setFiles() 
    {
        foreach( $_FILES as $key => $value ) {
            $this->files[ $key ] = $value;
        }
    }

    public static function base() 
    {
        $request = Request::getInstance();
        return $request->base;
    }

    public static function uri() 
    {
        $request = Request::getInstance();
        return $request->uri;
    }

    public static function method() 
    {
        $request = Request::getInstance();
        return $request->method;
    }

    public static function protocol() 
    {
        $request = Request::getInstance();
        return $request->protocol;
    }

    public static function server()
    {
        $request = Request::getInstance();
        return $request->server;
    }

    public function port()
    {
        $request = Request::getInstance();
        return $request->port;
    }
    
    public static function all() 
    {
        $request = Request::getInstance();
        return $request->data;
    }

    public static function asset( $resource )
    {
        $request = Request::getInstance();

        if ( substr( $resource, 0, 1 ) == '/' ) {
            $resource = substr( $resource, 1, strlen( $resource ) - 1);
        }
        return $request->base . $resource;
    }

    public function __isset( $key ) 
    {
        return isset( $this->data[$key] );
    }

    public function __get( $key ) 
    {
        if ( isset( $this->data[$key] )) {
            return $this->data[$key];
        }
    }

    public function hasFile( $key ) 
    {
        return isset( $this->files[$key] );
    }

    public function file( $key ) 
    {
        if ( isset( $this->files[$key] )) {
            return $this->files[$key];
        }
    }
}