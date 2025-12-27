<?php

declare(strict_types=1);

define('PHPUNIT_TEST', true);

// ini_set('display_errors', '1');
// ini_set('error_reporting', E_ALL);

// Define BASE_PATH se não estiver definido
if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__, 1));
}

$autoload_path = BASE_PATH . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
require_once $autoload_path;

$models_path = BASE_PATH . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR . 'App'. DIRECTORY_SEPARATOR . 'Models' . DIRECTORY_SEPARATOR;
require_once $models_path . 'User.php';
require_once $models_path . 'Role.php';
require_once $models_path . 'Permission.php';

use Slendie\Controllers\Controller;
use Slendie\Controllers\Middlewares\WebMiddleware;
use Slendie\Framework\Autoloader;
use Slendie\Framework\Database;
use Slendie\Framework\Env;
use Slendie\Framework\Request;

// Registra o autoloader customizado
Autoloader::register();

Env::set('APP_NAME', 'Test App');
Env::set('DB_CONNECTION', 'sqlite');
Env::set('DB_DATABASE', ':memory:');
Env::set('TIMEZONE', 'UTC');

date_default_timezone_set('UTC');

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

// pest()->extend(Tests\TestCase::class)->in('Feature');


/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

// Função auxiliar para limpar WebMiddleware
function cleanupWebMiddleware()
{
    try {
        $reflection = new ReflectionClass(\Slendie\Controllers\Middlewares\WebMiddleware::class);
        $property = $reflection->getProperty('request');
        $property->setAccessible(true);
        $property->setValue(null, null);
    } catch (Throwable $e) {
        // Ignora erros de limpeza
    }

    // Garante que $_SERVER não contenha objetos Request acidentalmente
    // Isso pode acontecer se algum teste modificar $_SERVER incorretamente
    // Também garante que $_SERVER seja sempre um array
    if (!is_array($_SERVER)) {
        $_SERVER = [];
    } else {
        foreach ($_SERVER as $key => $value) {
            if (is_object($value)) {
                unset($_SERVER[$key]);
            }
        }
    }
}

// Limpa WebMiddleware após cada teste para evitar problemas durante o shutdown
// Isso previne que objetos Request sejam serializados quando o Pest tenta exibir erros
afterEach(function () {
    cleanupWebMiddleware();
});

// Também limpa no início de cada teste para garantir estado limpo
beforeEach(function () {
    cleanupWebMiddleware();
});

// Função auxiliar para limpar sessão
function clearSession()
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    unset($_SESSION['user_id']);
    unset($_SESSION['form_errors']);
    unset($_SESSION['form_success']);
    unset($_SESSION['old_input']);
}

// Função auxiliar para configurar Request no WebMiddleware
function setupRequest($method = 'GET', $uri = '/', $post = [], $get = [])
{
    // Salva valores originais
    $originalServer = $_SERVER;
    $originalGet = $_GET;
    $originalPost = $_POST;

    // Limpa
    $_SERVER = [];
    $_GET = [];
    $_POST = [];

    // Configura método e URI
    $_SERVER['REQUEST_METHOD'] = $method;
    $_SERVER['REQUEST_URI'] = $uri;
    $_GET = $get;
    $_POST = $post;

    $request = new Request();

    // Injeta no WebMiddleware
    $webMiddleware = new WebMiddleware();
    $webMiddleware->handle($request);

    return ['request' => $request, 'original' => ['server' => $originalServer, 'get' => $originalGet, 'post' => $originalPost]];
}

// Função auxiliar para simular requisição HTTP
// Retorna array com 'request' (objeto Request) e 'original' (valores originais para restore)
function simulateRequest($method = 'GET', $uri = '/', $get = [], $post = [], $files = [], $headers = [], $jsonBody = null)
{
    // Salva valores originais
    $originalServer = $_SERVER;
    $originalGet = $_GET;
    $originalPost = $_POST;
    $originalFiles = $_FILES;

    // Limpa
    $_SERVER = [];
    $_GET = [];
    $_POST = [];
    $_FILES = [];

    // Configura método e URI
    $_SERVER['REQUEST_METHOD'] = $method;
    $_SERVER['REQUEST_URI'] = $uri;

    // Configura GET
    $_GET = $get;

    // Configura POST
    $_POST = $post;

    // Configura FILES
    $_FILES = $files;

    // Configura headers
    foreach ($headers as $key => $value) {
        $headerKey = 'HTTP_' . mb_strtoupper(str_replace('-', '_', $key));
        $_SERVER[$headerKey] = $value;
    }

    // Configura JSON body se fornecido
    if ($jsonBody !== null) {
        // Simula php://input usando um arquivo temporário
        $tempFile = sys_get_temp_dir() . '/php_input_' . uniqid() . '.tmp';
        file_put_contents($tempFile, is_string($jsonBody) ? $jsonBody : json_encode($jsonBody));

        // Substitui file_get_contents('php://input') temporariamente
        // Nota: Não podemos realmente substituir php://input, então vamos usar reflection
        // ou criar um mock. Por enquanto, vamos testar sem JSON body primeiro.
    }

    $request = new Request();

    // Restaura valores originais
    $_SERVER = $originalServer;
    $_GET = $originalGet;
    $_POST = $originalPost;
    $_FILES = $originalFiles;

    // Retorna o objeto Request (compatibilidade com testes existentes)
    // NOTA: Para restaurar valores originais, use restoreRequest() com os valores salvos
    return $request;
}


// Função auxiliar para restaurar requisição
// Mantida para compatibilidade com testes existentes
function restoreRequest($originalServer)
{
    // Se $originalServer for um objeto Request, apenas limpa WebMiddleware
    // Isso previne o erro "Cannot use object as array" quando $_SERVER é atribuído incorretamente
    if (is_object($originalServer) && $originalServer instanceof \Slendie\Framework\Request) {
        cleanupWebMiddleware();
        return;
    }
    // Se for um array, restaura $_SERVER (comportamento original)
    if (is_array($originalServer)) {
        $_SERVER = $originalServer;
    }
    // Sempre limpa WebMiddleware para garantir que não haja objetos Request armazenados
    cleanupWebMiddleware();
}


// Função auxiliar para restaurar ambiente
function restoreEnvironment($original)
{
    $_SERVER = $original['server'];
    $_GET = $original['get'];
    $_POST = $original['post'];
}

// Função auxiliar para resetar conexão do Database
function resetDatabaseConnection()
{
    $reflection = new ReflectionClass(Database::class);
    $property = $reflection->getProperty('pdo');
    $property->setAccessible(true);
    $property->setValue(null, null);
}

// Função auxiliar para configurar ambiente de teste
function setupTestEnv(string $connection = 'sqlite', string $database = ':memory:')
{
    // Configura variáveis de ambiente para SQLite em memória
    Env::set('DB_CONNECTION', $connection);
    Env::set('DB_DATABASE', $database);
    Env::set('DB_HOST', '');
    Env::set('DB_PORT', '');
    Env::set('DB_USER', '');
    Env::set('DB_PASSWORD', '');

    // Reseta conexão do Database
    resetDatabaseConnection();
}

// Função auxiliar para criar tabelas de teste
function createTestTables()
{
    $pdo = Database::getConnection();

    // Cria tabela de usuários
    $pdo->exec('CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        email TEXT NOT NULL UNIQUE,
        password TEXT NOT NULL,
        role_id INTEGER
    )');

    // Cria tabela de roles
    $pdo->exec('CREATE TABLE IF NOT EXISTS roles (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL
    )');

    // Cria tabela de permissions
    $pdo->exec('CREATE TABLE IF NOT EXISTS permissions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL UNIQUE
    )');

    // Cria tabela de role_permissions
    $pdo->exec('CREATE TABLE IF NOT EXISTS role_permissions (
        role_id INTEGER NOT NULL,
        permission_id INTEGER NOT NULL
    )');
}

// Função auxiliar para limpar tabelas
function clearTestTables()
{
    $pdo = Database::getConnection();
    $pdo->exec('DELETE FROM role_permissions');
    $pdo->exec('DELETE FROM users');
    $pdo->exec('DELETE FROM roles');
    $pdo->exec('DELETE FROM permissions');
}

// Função auxiliar para capturar output e código HTTP
function captureOutputAndCode($callback)
{
    ob_start();
    $code = null;
    $output = '';
    $result = null;

    try {
        // Captura código HTTP usando output buffering
        $result = $callback();
        $output = ob_get_clean();
    } catch (Throwable $e) {
        // Garante que o buffer seja limpo mesmo em caso de exceção
        if (ob_get_level() > 0) {
            $output = ob_get_clean();
        }
        throw $e;
    } finally {
        // Garante limpeza do buffer mesmo se ob_get_clean() falhar
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
    }

    // Tenta obter o código HTTP (pode não estar disponível em todos os ambientes)
    if (function_exists('http_response_code')) {
        $code = http_response_code();
    }

    return ['result' => $result, 'output' => $output, 'code' => $code];
}

// Função auxiliar para remover diretório recursivamente
function removeDirectory($dir)
{
    if (!is_dir($dir)) {
        return;
    }
    try {
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            if (is_dir($path)) {
                removeDirectory($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    } catch (Exception $e) {
        // Ignora erros de limpeza
    }
}

// Função auxiliar para simular JSON body (mais complexo, vamos testar separadamente)
function simulateRequestWithJson($jsonBody, $method = 'POST', $uri = '/', $get = [], $post = [], $files = [], $headers = [])
{
    // Salva valores originais
    $originalServer = $_SERVER;
    $originalGet = $_GET;
    $originalPost = $_POST;
    $originalFiles = $_FILES;

    // Limpa
    $_SERVER = [];
    $_GET = [];
    $_POST = [];
    $_FILES = [];

    // Configura método e URI
    $_SERVER['REQUEST_METHOD'] = $method;
    $_SERVER['REQUEST_URI'] = $uri;

    // Configura GET
    $_GET = $get;

    // Configura POST
    $_POST = $post;

    // Configura FILES
    $_FILES = $files;

    // Configura headers
    foreach ($headers as $key => $value) {
        $headerKey = 'HTTP_' . mb_strtoupper(str_replace('-', '_', $key));
        $_SERVER[$headerKey] = $value;
    }

    // Para JSON, precisamos simular php://input
    // Vamos criar um stream wrapper temporário ou usar reflection
    // Por enquanto, vamos apenas testar que o método json() existe

    $request = new Request();

    // Restaura valores originais
    $_SERVER = $originalServer;
    $_GET = $originalGet;
    $_POST = $originalPost;
    $_FILES = $originalFiles;

    return $request;
}

// Função auxiliar para configurar ambiente de email para testes
function setupMailEnv($config = [])
{
    $defaults = [
        'MAIL_HOST' => 'smtp.example.com',
        'MAIL_USERNAME' => 'user@example.com',
        'MAIL_PASSWORD' => 'password123',
        'MAIL_PORT' => 587,
        'MAIL_FROM_ADDRESS' => 'from@example.com',
        'MAIL_FROM_NAME' => 'Test Sender',
        'MAIL_AUTH' => 'true',
        'MAIL_ENCRYPTION' => 'tls'
    ];

    $merged = array_merge($defaults, $config);

    foreach ($merged as $key => $value) {
        if ($key === 'MAIL_PORT') {
            Env::set($key, (int)$value);
        } else {
            Env::set($key, (string)$value);
        }

    }
}

// Função auxiliar para limpar configurações de email
function cleanupMailEnv()
{
    $keys = [
        'MAIL_HOST', 'MAIL_USERNAME', 'MAIL_PASSWORD', 'MAIL_PORT',
        'MAIL_FROM_ADDRESS', 'MAIL_FROM_NAME', 'MAIL_AUTH', 'MAIL_ENCRYPTION'
    ];

    foreach ($keys as $key) {
        Env::set($key, '');
    }
}


// Carrega a classe TestController do namespace tests
require_once __DIR__ . DIRECTORY_SEPARATOR . 'TestController.php';

// Registra error handler para limpar WebMiddleware quando há erros fatais
// Isso previne que objetos Request sejam serializados durante o tratamento de erros
set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    // Limpa WebMiddleware apenas para erros fatais
    if (in_array($errno, [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE, E_RECOVERABLE_ERROR])) {
        cleanupWebMiddleware();
    }
    // Retorna false para permitir que o handler padrão também processe o erro
    return false;
}, E_ALL);

// Garantir que qualquer output buffering seja limpo no final
// e que objetos Request armazenados no WebMiddleware sejam limpos
// IMPORTANTE: Esta função deve ser registrada ANTES de qualquer outra shutdown function
// para garantir que seja executada primeiro
register_shutdown_function(function () {
    // Limpa o WebMiddleware IMEDIATAMENTE para evitar que objetos Request sejam serializados
    // durante o shutdown do Pest, especialmente quando há erros e o Pest tenta exibir informações
    // Isso previne o erro "Cannot use object of type Request as array" no OutputFormatterStyle
    cleanupWebMiddleware();

    // Limpa qualquer output buffering que possa ter ficado aberto
    while (ob_get_level() > 0) {
        @ob_end_flush();
    }
}, true); // true = registra no início da fila de shutdown functions
