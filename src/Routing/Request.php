<?php
namespace Slendie\Framework\Routing;

class Request
{
    protected $files;
    protected $base;
    protected $uri;
    protected $method;
    protected $protocol;
    protected $server;
    protected $data = [];
    protected $port;

    public function __construct() {
        // $this->base = $_SERVER['REQUEST_URI'];
        // $this->uri = $_REQUEST['uri'] ?? '/';
        if ( array_key_exists('SERVER_NAME', $_SERVER) ) {
            $this->base = $_SERVER['SERVER_NAME'];
        } else {
            $this->base = $_SERVER['REQUEST_URI'];
        }
        $this->uri = $_SERVER['REQUEST_URI'] ?? '/';
        $this->method = strtolower($_SERVER['REQUEST_METHOD']);
        $this->protocol = isset( $_SERVER['HTTPS'] ) ? 'https' : 'http';
        $this->port = $_SERVER['SERVER_PORT'];

        if ( $this->port != 80 || !empty( $this->port ) ) {
            $this->server = $this->base . ':' . $this->port . '/';
        } else {
            $this->server = $this->base . '/';
        }

        $this->setData();

        if ( count( $_FILES ) > 0 ) {
            $this->setFiles();
        }
    }

    protected function setData() 
    {
        switch ( $this->method ) {
            case 'post':
                $this->data = $_POST;
                break;

            case 'get':
                $this->data = $_GET;
                break;

            case 'head':
            case 'put':
            case 'delete':
            case 'options':
                parse_str( file_get_contents('php://input'), $this->data );
                break;
        }
    }

    protected function setFiles() 
    {
        foreach( $_FILES as $key => $value ) {
            $this->files[ $key ] = $value;
        }
    }

    public function base() 
    {
        return $this->base;
    }

    public function uri() 
    {
        return $this->uri;
    }

    public function method() 
    {
        return $this->method;
    }

    public function protocol() 
    {
        return $this->protocol;
    }

    public function server()
    {
        return $this->server;
    }

    public function port()
    {
        return $this->port;
    }
    
    public function all() 
    {
        return $this->data;
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