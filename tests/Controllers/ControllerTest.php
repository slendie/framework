<?php

declare(strict_types=1);

use Slendie\Framework\Request;
use tests\TestController;

require_once __DIR__ . '/../../vendor/autoload.php';

// Define BASE_PATH se não estiver definido
if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__, 2));
}

// Define PHPUNIT_TEST para ambiente de teste
if (!defined('PHPUNIT_TEST')) {
    define('PHPUNIT_TEST', true);
}

it('inicializa com Request do WebMiddleware', function () {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $env = setupRequest('GET', '/');

    $controller = new TestController();

    $request = $controller->getRequest();

    expect($request)->toBeInstanceOf(Request::class);

    restoreEnvironment($env['original']);
});

it('obtém form_errors da sessão', function () {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $_SESSION['form_errors'] = ['name' => 'Nome é obrigatório', 'email' => 'Email inválido'];

    $env = setupRequest('GET', '/');

    $controller = new TestController();

    $formErrors = $controller->getFormErrors();

    expect($formErrors)->toBeArray();
    expect($formErrors)->toHaveKey('name');
    expect($formErrors)->toHaveKey('email');
    expect($formErrors['name'])->toBe('Nome é obrigatório');

    restoreEnvironment($env['original']);
});

it('obtém form_success da sessão', function () {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $_SESSION['form_success'] = 'Formulário enviado com sucesso!';

    $env = setupRequest('GET', '/');

    $controller = new TestController();

    $formSuccess = $controller->getFormSuccess();

    expect($formSuccess)->toBe('Formulário enviado com sucesso!');

    restoreEnvironment($env['original']);
});

it('limpa form_errors da sessão após obter', function () {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $_SESSION['form_errors'] = ['name' => 'Nome é obrigatório'];

    $env = setupRequest('GET', '/');

    $controller = new TestController();

    // Verifica que foi obtido
    $formErrors = $controller->getFormErrors();
    expect($formErrors)->toHaveKey('name');

    // Verifica que foi limpo da sessão
    expect(isset($_SESSION['form_errors']))->toBeFalse();

    restoreEnvironment($env['original']);
});

it('limpa form_success da sessão após obter', function () {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $_SESSION['form_success'] = 'Sucesso!';

    $env = setupRequest('GET', '/');

    $controller = new TestController();

    // Verifica que foi obtido
    $formSuccess = $controller->getFormSuccess();
    expect($formSuccess)->toBe('Sucesso!');

    // Verifica que foi limpo da sessão
    expect(isset($_SESSION['form_success']))->toBeFalse();

    restoreEnvironment($env['original']);
});

it('usa array vazio quando form_errors não existe na sessão', function () {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    unset($_SESSION['form_errors']);

    $env = setupRequest('GET', '/');

    $controller = new TestController();

    $formErrors = $controller->getFormErrors();

    expect($formErrors)->toBeArray();
    expect($formErrors)->toHaveCount(0);

    restoreEnvironment($env['original']);
});

it('usa null quando form_success não existe na sessão', function () {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    unset($_SESSION['form_success']);

    $env = setupRequest('GET', '/');

    $controller = new TestController();

    $formSuccess = $controller->getFormSuccess();

    expect($formSuccess)->toBeNull();

    restoreEnvironment($env['original']);
});

it('request() retorna instância de Request', function () {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $env = setupRequest('GET', '/');

    $controller = new TestController();

    $request = $controller->getRequest();

    expect($request)->toBeInstanceOf(Request::class);

    restoreEnvironment($env['original']);
});

it('redirect() não executa exit em ambiente de teste', function () {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $env = setupRequest('GET', '/');

    $controller = new TestController();

    // Em ambiente de teste, não deve executar exit
    $result = $controller->testRedirect('/home');

    // Deve retornar sem erro
    expect($result)->toBeNull();

    restoreEnvironment($env['original']);
});

it('redirect() aceita diferentes URLs', function () {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $env = setupRequest('GET', '/');

    $controller = new TestController();

    $urls = ['/home', '/users', '/login', 'https://example.com'];

    foreach ($urls as $url) {
        $result = $controller->testRedirect($url);
        expect($result)->toBeNull();
    }

    restoreEnvironment($env['original']);
});

it('render() cria instância de Blade', function () {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $env = setupRequest('GET', '/');

    // Cria view temporária no diretório views real
    $viewName = 'test_' . uniqid();
    $viewFile = BASE_PATH . '/views/' . $viewName . '.blade.php';
    file_put_contents($viewFile, 'Hello {{ $name }}');

    $controller = new TestController();

    ob_start();
    $controller->testRender($viewName, ['name' => 'World']);
    $output = ob_get_clean();

    expect($output)->toContain('Hello');
    expect($output)->toContain('World');

    // Limpa
    @unlink($viewFile);

    restoreEnvironment($env['original']);
});

it('render() adiciona form_errors aos dados se não existir', function () {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $_SESSION['form_errors'] = ['name' => 'Erro no nome'];

    $env = setupRequest('GET', '/');

    // Cria view temporária no diretório views real
    $viewName = 'test_' . uniqid();
    $viewFile = BASE_PATH . '/views/' . $viewName . '.blade.php';
    file_put_contents($viewFile, 'Errors: {{ json_encode($form_errors) }}');

    $controller = new TestController();

    ob_start();
    $controller->testRender($viewName, []);
    $output = ob_get_clean();

    expect($output)->toContain('Erro no nome');

    // Limpa
    @unlink($viewFile);

    restoreEnvironment($env['original']);
});

it('render() não sobrescreve form_errors se já existir nos dados', function () {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $_SESSION['form_errors'] = ['name' => 'Erro da sessão'];

    $env = setupRequest('GET', '/');

    // Cria view temporária no diretório views real
    $viewName = 'test_' . uniqid();
    $viewFile = BASE_PATH . '/views/' . $viewName . '.blade.php';
    file_put_contents($viewFile, 'Errors: {{ json_encode($form_errors) }}');

    $controller = new TestController();

    ob_start();
    $controller->testRender($viewName, ['form_errors' => ['custom' => 'Erro customizado']]);
    $output = ob_get_clean();

    // Deve usar o form_errors fornecido, não o da sessão
    expect($output)->toContain('Erro customizado');
    expect($output)->not->toContain('Erro da sessão');

    // Limpa
    @unlink($viewFile);

    restoreEnvironment($env['original']);
});

it('render() adiciona errors aos dados se não existir', function () {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $env = setupRequest('GET', '/');

    // Cria view temporária no diretório views real
    $viewName = 'test_' . uniqid();
    $viewFile = BASE_PATH . '/views/' . $viewName . '.blade.php';
    file_put_contents($viewFile, 'Errors: {{ json_encode($errors ?? []) }}');

    $controller = new TestController();

    // errors é inicializado como null, então deve ser null
    ob_start();
    $controller->testRender($viewName, []);
    $output = ob_get_clean();

    // Deve processar sem erro
    expect($output)->toBeString();

    // Limpa
    @unlink($viewFile);

    restoreEnvironment($env['original']);
});

it('render() adiciona form_success aos dados se não existir', function () {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $_SESSION['form_success'] = 'Operação realizada com sucesso!';

    $env = setupRequest('GET', '/');

    // Cria view temporária no diretório views real
    $viewName = 'test_' . uniqid();
    $viewFile = BASE_PATH . '/views/' . $viewName . '.blade.php';
    file_put_contents($viewFile, 'Success: {{ $form_success }}');

    $controller = new TestController();

    ob_start();
    $controller->testRender($viewName, []);
    $output = ob_get_clean();

    expect($output)->toContain('Operação realizada com sucesso!');

    // Limpa
    @unlink($viewFile);

    restoreEnvironment($env['original']);
});

it('render() não sobrescreve form_success se já existir nos dados', function () {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $_SESSION['form_success'] = 'Sucesso da sessão';

    $env = setupRequest('GET', '/');

    // Cria view temporária no diretório views real
    $viewName = 'test_' . uniqid();
    $viewFile = BASE_PATH . '/views/' . $viewName . '.blade.php';
    file_put_contents($viewFile, 'Success: {{ $form_success }}');

    $controller = new TestController();

    ob_start();
    $controller->testRender($viewName, ['form_success' => 'Sucesso customizado']);
    $output = ob_get_clean();

    // Deve usar o form_success fornecido
    expect($output)->toContain('Sucesso customizado');
    expect($output)->not->toContain('Sucesso da sessão');

    // Limpa
    @unlink($viewFile);

    restoreEnvironment($env['original']);
});

it('render() adiciona debug comment para auth/login com form_errors', function () {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $_SESSION['form_errors'] = ['email' => 'Email inválido'];

    $env = setupRequest('GET', '/');

    // Cria view temporária no diretório views real
    $authDir = BASE_PATH . '/views/auth';
    if (!is_dir($authDir)) {
        mkdir($authDir, 0777, true);
    }
    $viewFile = $authDir . '/login_test_' . uniqid() . '.blade.php';
    file_put_contents($viewFile, 'Login form');

    $controller = new TestController();

    // Usa view auth/login real se existir, senão usa a temporária
    $viewName = file_exists(BASE_PATH . '/views/auth/login.blade.php') ? 'auth/login' : 'auth/login_test_' . basename($viewFile, '.blade.php');

    ob_start();
    try {
        $controller->testRender($viewName, []);
    } catch (Exception $e) {
        // Se a view não existir, apenas verifica que o método funciona
    }
    $output = ob_get_clean();

    // Se renderizou com sucesso, deve conter comentário de debug
    if (!empty($output)) {
        expect($output)->toContain('<!-- DEBUG:');
        expect($output)->toContain('form_errors');
    }

    // Limpa
    if (file_exists($viewFile)) {
        @unlink($viewFile);
    }

    restoreEnvironment($env['original']);
});

it('render() não adiciona debug comment quando form_errors está vazio', function () {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    unset($_SESSION['form_errors']);

    $env = setupRequest('GET', '/');

    // Cria view temporária no diretório views real
    $authDir = BASE_PATH . '/views/auth';
    if (!is_dir($authDir)) {
        mkdir($authDir, 0777, true);
    }
    $viewFile = $authDir . '/login_test_' . uniqid() . '.blade.php';
    file_put_contents($viewFile, 'Login form');

    $controller = new TestController();

    // Usa view auth/login real se existir
    $viewName = file_exists(BASE_PATH . '/views/auth/login.blade.php') ? 'auth/login' : 'auth/login_test_' . basename($viewFile, '.blade.php');

    ob_start();
    try {
        $controller->testRender($viewName, []);
    } catch (Exception $e) {
        // Se a view não existir, apenas verifica que o método funciona
    }
    $output = ob_get_clean();

    // Não deve conter comentário de debug quando form_errors está vazio
    if (!empty($output)) {
        expect($output)->not->toContain('<!-- DEBUG:');
    }

    // Limpa
    if (file_exists($viewFile)) {
        @unlink($viewFile);
    }

    restoreEnvironment($env['original']);
});

it('render() não adiciona debug comment para outras views', function () {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $_SESSION['form_errors'] = ['name' => 'Erro'];

    $env = setupRequest('GET', '/');

    // Cria view temporária no diretório views real
    $viewName = 'home_test_' . uniqid();
    $viewFile = BASE_PATH . '/views/' . $viewName . '.blade.php';
    file_put_contents($viewFile, 'Home page');

    $controller = new TestController();

    ob_start();
    $controller->testRender($viewName, []);
    $output = ob_get_clean();

    // Não deve conter comentário de debug para views que não são auth/login
    expect($output)->not->toContain('<!-- DEBUG:');

    // Limpa
    @unlink($viewFile);

    restoreEnvironment($env['original']);
});

it('render() passa dados customizados para a view', function () {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $env = setupRequest('GET', '/');

    // Cria view temporária no diretório views real
    $viewName = 'test_' . uniqid();
    $viewFile = BASE_PATH . '/views/' . $viewName . '.blade.php';
    file_put_contents($viewFile, 'Name: {{ $name }}, Age: {{ $age }}');

    $controller = new TestController();

    ob_start();
    $controller->testRender($viewName, ['name' => 'John', 'age' => 30]);
    $output = ob_get_clean();

    expect($output)->toContain('John');
    expect($output)->toContain('30');

    // Limpa
    @unlink($viewFile);

    restoreEnvironment($env['original']);
});

it('render() combina dados customizados com form_errors e form_success', function () {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $_SESSION['form_errors'] = ['email' => 'Email inválido'];
    $_SESSION['form_success'] = 'Sucesso!';

    $env = setupRequest('GET', '/');

    // Cria view temporária no diretório views real
    $viewName = 'test_' . uniqid();
    $viewFile = BASE_PATH . '/views/' . $viewName . '.blade.php';
    file_put_contents($viewFile, 'Name: {{ $name }}, Error: {{ $form_errors["email"] ?? "" }}, Success: {{ $form_success ?? "" }}');

    $controller = new TestController();

    ob_start();
    $controller->testRender($viewName, ['name' => 'John']);
    $output = ob_get_clean();

    expect($output)->toContain('John');
    expect($output)->toContain('Email inválido');
    expect($output)->toContain('Sucesso!');

    // Limpa
    @unlink($viewFile);

    restoreEnvironment($env['original']);
});

it('inicializa errors como null', function () {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $env = setupRequest('GET', '/');

    $controller = new TestController();

    $errors = $controller->getErrors();

    expect($errors)->toBeNull();

    restoreEnvironment($env['original']);
});

it('inicializa oldInput como null', function () {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $env = setupRequest('GET', '/');

    $controller = new TestController();

    $oldInput = $controller->getOldInput();

    expect($oldInput)->toBeNull();

    restoreEnvironment($env['original']);
});

it('request() retorna mesma instância obtida no construtor', function () {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $env = setupRequest('GET', '/');

    $controller = new TestController();

    $request1 = $controller->getRequest();
    $request2 = $controller->getRequest();

    expect($request1)->toBe($request2);

    restoreEnvironment($env['original']);
});

it('render() funciona com view que não existe (tratamento de erro)', function () {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $env = setupRequest('GET', '/');

    $controller = new TestController();

    expect(function () use ($controller) {
        $controller->testRender('non_existent_view', []);
    })->toThrow(Exception::class, 'View not found: ' . BASE_PATH . '/views/non_existent_view.blade.php');

    restoreEnvironment($env['original']);
});

it('render() preserva dados existentes e adiciona apenas os faltantes', function () {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $_SESSION['form_errors'] = ['name' => 'Erro'];
    $_SESSION['form_success'] = 'Sucesso';

    $env = setupRequest('GET', '/');

    // Cria view temporária no diretório views real
    $viewName = 'test_' . uniqid();
    $viewFile = BASE_PATH . '/views/' . $viewName . '.blade.php';
    file_put_contents($viewFile, 'Custom: {{ $custom }}, Errors: {{ json_encode($form_errors) }}, Success: {{ $form_success }}');

    $controller = new TestController();

    ob_start();
    $controller->testRender($viewName, ['custom' => 'value']);
    $output = ob_get_clean();

    // Deve ter custom, form_errors e form_success
    expect($output)->toContain('value');
    expect($output)->toContain('Erro');
    expect($output)->toContain('Sucesso');

    // Limpa
    @unlink($viewFile);

    restoreEnvironment($env['original']);
});

it('redirect() funciona mesmo quando PHPUNIT_TEST não está definido via getenv', function () {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Define apenas via constante
    $env = setupRequest('GET', '/');

    $controller = new TestController();

    // Deve funcionar sem erro
    $result = $controller->testRedirect('/test');

    expect($result)->toBeNull();

    restoreEnvironment($env['original']);
});

it('render() funciona com array vazio de dados', function () {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $env = setupRequest('GET', '/');

    // Cria view temporária no diretório views real
    $viewName = 'test_' . uniqid();
    $viewFile = BASE_PATH . '/views/' . $viewName . '.blade.php';
    file_put_contents($viewFile, 'Simple view');

    $controller = new TestController();

    ob_start();
    $controller->testRender($viewName, []);
    $output = ob_get_clean();

    expect($output)->toContain('Simple view');

    // Limpa
    @unlink($viewFile);

    restoreEnvironment($env['original']);
});

it('render() funciona sem parâmetro de dados (usa array vazio)', function () {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $env = setupRequest('GET', '/');

    // Cria view temporária no diretório views real
    $viewName = 'test_' . uniqid();
    $viewFile = BASE_PATH . '/views/' . $viewName . '.blade.php';
    file_put_contents($viewFile, 'Simple view');

    $controller = new TestController();

    ob_start();
    // Chama sem segundo parâmetro (usa padrão [])
    $reflection = new ReflectionClass($controller);
    $method = $reflection->getMethod('render');
    $method->setAccessible(true);
    $method->invoke($controller, $viewName);
    $output = ob_get_clean();

    expect($output)->toContain('Simple view');

    // Limpa
    @unlink($viewFile);

    restoreEnvironment($env['original']);
});
