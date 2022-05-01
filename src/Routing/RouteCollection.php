<?php
namespace Slendie\Framework\Routing;

class RouteCollection
{
    protected $routes_post = [];
    protected $routes_get = [];
    protected $routes_put = [];
    protected $routes_delete = [];
    protected $route_names = [];

    public function add($request_method, $pattern, $callback) 
    {
        switch( strtolower($request_method) ) {
            case 'post':
                return $this->addPost($pattern, $callback);
                break;

            case 'get':
                return $this->addGet($pattern, $callback);
                break;

            case 'put':
                return $this->addPut($pattern, $callback);
                break;

            case 'delete':
                return $this->addDelete($pattern, $callback);
                break;
        }
    }

    protected function addPost($pattern, $callback) 
    {
        if ( is_array($pattern) ) {
            $settings = $this->parsePattern($pattern);
            $pattern = $settings['set'];
        } else {
            $settings = [];
        }
        $values = $this->toMap( $pattern );
        $route_arr = [
            'callback'  => $callback, 
            'values'    => $values, 
            'namespace' => $settings['namespace'] ?? null
        ];
        $this->routes_post[$this->definePattern( $pattern )] = $route_arr;

        if ( isset( $settings['as'] )) {
            $this->route_names[$settings['as']] = $pattern;
        }
        return $this;
    }
    protected function addGet($pattern, $callback) 
    {
        if ( is_array($pattern) ) {
            $settings = $this->parsePattern($pattern);
            $pattern = $settings['set'];
        } else {
            $settings = [];
        }
        $values = $this->toMap( $pattern );
        $route_arr = [
            'callback'  => $callback, 
            'values'    => $values, 
            'namespace' => $settings['namespace'] ?? null
        ];
        $this->routes_get[$this->definePattern( $pattern )] = $route_arr;

        if ( isset( $settings['as'] )) {
            $this->route_names[$settings['as']] = $pattern;
        }
        return $this;
    }
    protected function addPut($pattern, $callback) 
    {
        if ( is_array($pattern) ) {
            $settings = $this->parsePattern($pattern);
            $pattern = $settings['set'];
        } else {
            $settings = [];
        }
        $values = $this->toMap( $pattern );
        $route_arr = [
            'callback'  => $callback, 
            'values'    => $values, 
            'namespace' => $settings['namespace'] ?? null
        ];
        $this->routes_put[$this->definePattern( $pattern )] = $route_arr;

        if ( isset( $settings['as'] )) {
            $this->route_names[$settings['as']] = $pattern;
        }
        return $this;
    }
    protected function addDelete($pattern, $callback) 
    {
        if ( is_array($pattern) ) {
            $settings = $this->parsePattern($pattern);
            $pattern = $settings['set'];
        } else {
            $settings = [];
        }
        $values = $this->toMap( $pattern );
        $route_arr = [
            'callback'  => $callback, 
            'values'    => $values, 
            'namespace' => $settings['namespace'] ?? null
        ];
        $this->routes_delete[$this->definePattern( $pattern )] = $route_arr;

        if ( isset( $settings['as'] )) {
            $this->route_names[$settings['as']] = $pattern;
        }
        return $this;
    }

    protected function definePattern( $pattern ) 
    {
        $pattern = implode('/', array_filter( explode('/', $pattern) ));
        $pattern = '/^' . str_replace('/', '\/', $pattern) . '$/';

        $word = '[A-Za-z0-9\_\-]{1,}';
        if ( preg_match('/\{' . $word . '\}/', $pattern) ) {
            $pattern = preg_replace( '/\{' . $word . '\}/', $word, $pattern );
        }
        return $pattern;
    }

    protected function parsePattern( array $pattern )
    {
        // Define the pattern
        $result['set'] = $pattern['set'] ?? null;

        // Allows route name settings
        $result['as'] = $pattern['as'] ?? null;

        // Allows new namespace definition for Controllers
        $result['namespace'] = $pattern['namespace'] ?? null;

        return $result;
    }

    public function where( $request_method, $pattern ) 
    {
        switch( $request_method ) {
            case 'post':
                return $this->findPost( $pattern );
                break;

            case 'get':
                return $this->findGet( $pattern );
                break;

            case 'put':
                return $this->findPut( $pattern );
                break;

            case 'delete':
                return $this->findDelete( $pattern );
                break;

            default:
                throw new \Exception('Tipo de requisição não suportada');
        }
    }

    protected function parseUri( $uri ) 
    {
        return implode( '/', array_filter( explode('/', $uri)));
    }

    protected function findPost( $pattern_sent ) 
    {
        $pattern_sent = $this->parseUri( $pattern_sent );

        foreach( $this->routes_post as $pattern => $callback ) {
            if ( preg_match( $pattern, $pattern_sent, $pieces )) {
                return (object) ['callback' => $callback, 'uri' => $pieces];
            }
        }
        return false;
    }
    
    protected function findGet( $pattern_sent ) 
    {
        $pattern_sent = $this->parseUri( $pattern_sent );

        foreach( $this->routes_get as $pattern => $callback ) {
            if ( preg_match( $pattern, $pattern_sent, $pieces )) {
                return (object) ['callback' => $callback, 'uri' => $pieces];
            }
        }
        return false;
    }
    protected function findPut( $pattern_sent ) 
    {
        $pattern_sent = $this->parseUri( $pattern_sent );

        foreach( $this->routes_put as $pattern => $callback ) {
            if ( preg_match( $pattern, $pattern_sent, $pieces )) {
                return (object) ['callback' => $callback, 'uri' => $pieces];
            }
        }
        return false;
    }
    protected function findDelete( $pattern_sent ) 
    {
        $pattern_sent = $this->parseUri( $pattern_sent );

        foreach( $this->routes_delete as $pattern => $callback ) {
            if ( preg_match( $pattern, $pattern_sent, $pieces )) {
                return (object) ['callback' => $callback, 'uri' => $pieces];
            }
        }
        return false;
    }

    protected function strposarray( String $haystack, array $needles, int $offset = 0 ) 
    {
        $result = false;
        if (strlen($haystack) > 0 && count($needles) > 0) {
            foreach( $needles as $element ) {
                $result = strpos($haystack, $element, $offset);

                if ( false !== $result ) {
                    break;
                }
            }
        }
    }

    protected function toMap( $pattern ) 
    {
        $result = [];
        $needles = ['{', '[', '(', "\\"];
        $pattern = array_filter(explode('/', $pattern));

        foreach( $pattern as $key => $element ) {
            $found = $this->strposarray( $element, $needles );

            if ( false !== $found ) {
                if ( substr( $element, 0, 1 ) == '{' ) {
                    $result[preg_filter('/([\{\}])/', '', $element)] = $key - 1;
                } else {
                    $index = 'value_' . !empty($result) ? count( $result ) + 1 : 1;
                    array_merge( $result, [$index => $key - 1] );
                }
            }
        }
        return count($result) > 0 ? $result : false;
    }

    public function isThereAnyHow( $name ) 
    {
        return $this->route_names[$name] ?? false;
    }

    public function convert( $pattern, $params ) 
    {
        if ( !is_array($params) ) {
            $params = array($params);
        }

        $positions = $this->toMap( $pattern );
        if ( false === $positions ) {
            $positions = [];
        }

        $pattern = array_filter(explode('/', $pattern));

        if ( count($positions) < count($pattern) ) {
            $uri = [];

            foreach( $pattern as $key => $element ) {
                if ( in_array($key - 1, $positions) ) {
                    $uri[] = array_shift( $params );
                } else {
                    $uri[] = $element;
                }
            }
            return implode('/', array_filter($uri));
        }

        return false;
    }
}