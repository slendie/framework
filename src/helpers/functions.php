<?php

declare(strict_types=1);

if (! function_exists('env')) {
    /**
     * Get the value of an environment variable
     *
     * @param string $key The environment variable key
     * @param mixed $default The default value if the variable is not set
     * @return mixed The value of the environment variable or the default value
     */
    function env(string $key, mixed $default = null)
    {
        return Slendie\Framework\Env::get($key, $default);
    }
}

if (! function_exists('config')) {
    /**
     * Get a configuration value
     *
     * @param string $key The configuration key (e.g., 'app.name')
     * @param mixed $default The default value if the key is not set
     * @return mixed The configuration value or the default value
     */
    function config($key, $default = null)
    {
        $keys = explode('.', $key);
        $configFile = $keys[0];
        $configPath = BASE_PATH . '/config/' . $configFile . '.php';

        if (!file_exists($configPath)) {
            return $default;
        }

        $config = require $configPath;

        // Remove the first key (config file name) from the array
        array_shift($keys);

        // Navigate through the nested array
        $value = $config;
        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return $default;
            }
            $value = $value[$k];
        }

        return $value;
    }
}

if (! function_exists('route')) {
    /**
     * Generate a URL for a named route
     *
     * Reads routes from config/routes.php and finds the route by name.
     * If the route has parameters, they can be passed as an array.
     *
     * @param string $name The route name
     * @param array $parameters Optional route parameters
     * @return string The route URL
     */
    function route($name, $parameters = [])
    {
        static $routesCache = null;

        // Load routes from config file (cache for performance)
        if ($routesCache === null) {
            $routesPath = BASE_PATH . '/config/routes.php';
            if (file_exists($routesPath)) {
                $routesCache = require $routesPath;
            } else {
                $routesCache = [];
            }
        }

        // Find route by name
        foreach ($routesCache as $route) {
            if (isset($route['name']) && $route['name'] === $name) {
                $url = $route['path'];

                // Replace route parameters if any
                if (!empty($parameters)) {
                    foreach ($parameters as $key => $value) {
                        $url = str_replace('{' . $key . '}', $value, $url);
                    }
                }

                return $url;
            }
        }

        // If route not found by name, return root as fallback
        return '/';
    }
}

if (! function_exists('app')) {
    /**
     * Get an application instance or call a method on it
     *
     * Simple helper to mimic Laravel's app() function
     *
     * @param string|null $method Optional method to call
     * @return mixed Application instance or method result
     */
    function app($method = null)
    {
        static $instance = null;

        if ($instance === null) {
            $instance = new class {
                public function getLocale()
                {
                    return env('APP_LOCALE', 'pt');
                }
            };
        }

        if ($method !== null) {
            return $instance->$method();
        }

        return $instance;
    }
}

if (! function_exists('old')) {
    /**
     * Get old input value from session (flash data)
     *
     * Retrieves a value from the old input stored in session, which is typically
     * set when a form submission fails validation. The value is automatically
     * removed from session after being retrieved (flash behavior).
     *
     * @param string $key The input key to retrieve
     * @param mixed $default The default value if the key doesn't exist
     * @return mixed The old input value, default value, or empty string
     */
    function old(string $key, mixed $default = null)
    {
        // Garante que a sessão esteja iniciada
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Verifica se existe old_input na sessão
        if (!isset($_SESSION['old_input']) || !is_array($_SESSION['old_input'])) {
            return $default ?? '';
        }

        // Verifica se a chave existe
        if (!isset($_SESSION['old_input'][$key])) {
            return $default ?? '';
        }

        // Recupera o valor
        $value = $_SESSION['old_input'][$key];

        // Não remove o valor imediatamente para permitir múltiplas leituras no mesmo request
        // O valor será removido automaticamente no próximo request ou manualmente com flash_clear()
        
        return $value;
    }
}

if (! function_exists('has_route')) {
    /**
     * Verifica se uma rota existe pelo nome
     *
     * @param string $name O nome da rota
     * @return bool True se a rota existe, false caso contrário
     */
    function has_route(string $name): bool
    {
        return Slendie\Framework\Router::hasRoute($name);
    }
}