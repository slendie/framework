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
        // Prevent access from CLI or CGI.
        if ( is_null( $_SERVER ) || !array_key_exists('SERVER_NAME', $_SERVER) ) {
            return;
        }

        // $this->base = $_SERVER['REQUEST_URI'];
        // $this->uri = $_REQUEST['uri'] ?? '/';
        $this->base = $_SERVER['SERVER_NAME'];

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