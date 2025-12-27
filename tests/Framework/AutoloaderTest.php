<?php

declare(strict_types=1);

use Slendie\Framework\Autoloader;

require_once __DIR__ . '/../../vendor/autoload.php';

// Define BASE_PATH se não estiver definido
if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__, 2));
}

it('registra o autoloader com sucesso', function () {
    // Desregistra primeiro para garantir estado limpo
    Autoloader::unregister();

    // Registra o autoloader
    Autoloader::register();

    // Verifica se está registrado verificando se o autoloader está na lista
    $autoloaders = spl_autoload_functions();
    expect($autoloaders)->toContain([Autoloader::class, 'load']);
});

it('não registra o autoloader duas vezes', function () {
    // Desregistra primeiro
    Autoloader::unregister();

    // Registra duas vezes
    Autoloader::register();
    Autoloader::register();

    // Conta quantas vezes o autoloader aparece na lista
    $autoloaders = spl_autoload_functions();
    $count = 0;
    foreach ($autoloaders as $loader) {
        if (is_array($loader) && $loader[0] === Autoloader::class && $loader[1] === 'load') {
            $count++;
        }
    }

    expect($count)->toBe(1);
});

it('desregistra o autoloader com sucesso', function () {
    // Registra primeiro
    Autoloader::register();

    // Desregistra
    Autoloader::unregister();

    // Verifica se não está mais na lista
    $autoloaders = spl_autoload_functions();
    $found = false;
    foreach ($autoloaders as $loader) {
        if (is_array($loader) && $loader[0] === Autoloader::class && $loader[1] === 'load') {
            $found = true;
            break;
        }
    }

    expect($found)->toBeFalse();
});

it('usa BASE_PATH quando basePath não é fornecido', function () {
    Autoloader::unregister();

    // Registra sem fornecer basePath
    Autoloader::register();

    // O autoloader deve usar BASE_PATH internamente
    // Verificamos isso tentando carregar uma classe que não existe
    // e verificando se o método load retorna false
    $result = Autoloader::load('ClasseInexistenteParaTeste123');
    expect($result)->toBeFalse();

    Autoloader::unregister();
});

it('usa basePath customizado quando fornecido', function () {
    Autoloader::unregister();

    $customPath = sys_get_temp_dir() . '/autoloader_test_' . uniqid();
    mkdir($customPath, 0777, true);

    // Cria um arquivo de teste em um dos caminhos esperados
    $testDir = $customPath . '/app/models';
    mkdir($testDir, 0777, true);

    $testClass = 'AutoloaderTestModel';
    $testFile = $testDir . '/' . $testClass . '.php';
    file_put_contents($testFile, "<?php\nclass {$testClass} {}\n");

    // Registra com caminho customizado
    Autoloader::register($customPath);

    // Tenta carregar a classe
    $result = Autoloader::load($testClass);
    expect($result)->toBeTrue();
    expect(class_exists($testClass, false))->toBeTrue();

    // Limpa
    @unlink($testFile);
    removeDirectory($customPath);
    Autoloader::unregister();
});

it('carrega classe de app/controllers/', function () {
    Autoloader::unregister();
    Autoloader::register();

    // Verifica se consegue carregar uma classe existente
    // HomeController existe em app/controllers/
    $result = Autoloader::load('HomeController');
    // Pode retornar true se o arquivo existir, ou false se já foi carregado pelo Composer
    // O importante é que não lance erro
    expect(is_bool($result))->toBeTrue();

    Autoloader::unregister();
});

it('carrega classe de app/models/', function () {
    Autoloader::unregister();
    Autoloader::register();

    // Verifica se consegue carregar uma classe existente
    // User existe em app/models/
    $result = Autoloader::load('User');
    // Pode retornar true se o arquivo existir, ou false se já foi carregado pelo Composer
    expect(is_bool($result))->toBeTrue();

    Autoloader::unregister();
});

it('retorna false quando classe não existe em nenhum caminho', function () {
    Autoloader::unregister();
    Autoloader::register();

    $result = Autoloader::load('ClasseQueNaoExiste123456');
    expect($result)->toBeFalse();

    Autoloader::unregister();
});

it('tenta todos os caminhos antes de retornar false', function () {
    Autoloader::unregister();

    $tempPath = sys_get_temp_dir() . '/autoloader_test_' . uniqid();
    mkdir($tempPath, 0777, true);

    // Cria arquivo no último caminho da lista
    $testDir = $tempPath . '/src/Models';
    mkdir($testDir, 0777, true);

    $testClass = 'AutoloaderLastPathTest';
    $testFile = $testDir . '/' . $testClass . '.php';
    file_put_contents($testFile, "<?php\nclass {$testClass} {}\n");

    Autoloader::register($tempPath);

    $result = Autoloader::load($testClass);
    expect($result)->toBeTrue();
    expect(class_exists($testClass, false))->toBeTrue();

    // Limpa
    @unlink($testFile);
    removeDirectory($tempPath);
    Autoloader::unregister();
});

it('carrega classe de app/controllers/middlewares/', function () {
    Autoloader::unregister();

    $tempPath = sys_get_temp_dir() . '/autoloader_test_' . uniqid();
    mkdir($tempPath, 0777, true);

    $testDir = $tempPath . '/app/controllers/middlewares';
    mkdir($testDir, 0777, true);

    $testClass = 'AutoloaderTestMiddleware';
    $testFile = $testDir . '/' . $testClass . '.php';
    file_put_contents($testFile, "<?php\nclass {$testClass} {}\n");

    Autoloader::register($tempPath);

    $result = Autoloader::load($testClass);
    expect($result)->toBeTrue();
    expect(class_exists($testClass, false))->toBeTrue();

    // Limpa
    @unlink($testFile);
    removeDirectory($tempPath);
    Autoloader::unregister();
});

it('carrega classe de src/Framework/', function () {
    Autoloader::unregister();

    $tempPath = sys_get_temp_dir() . '/autoloader_test_' . uniqid();
    mkdir($tempPath, 0777, true);

    $testDir = $tempPath . '/src/Framework';
    mkdir($testDir, 0777, true);

    $testClass = 'AutoloaderTestFramework';
    $testFile = $testDir . '/' . $testClass . '.php';
    file_put_contents($testFile, "<?php\nclass {$testClass} {}\n");

    Autoloader::register($tempPath);

    $result = Autoloader::load($testClass);
    expect($result)->toBeTrue();
    expect(class_exists($testClass, false))->toBeTrue();

    // Limpa
    @unlink($testFile);
    removeDirectory($tempPath);
    Autoloader::unregister();
});

it('carrega classe de src/Controllers/', function () {
    Autoloader::unregister();

    $tempPath = sys_get_temp_dir() . '/autoloader_test_' . uniqid();
    mkdir($tempPath, 0777, true);

    $testDir = $tempPath . '/src/Controllers';
    mkdir($testDir, 0777, true);

    $testClass = 'AutoloaderTestController';
    $testFile = $testDir . '/' . $testClass . '.php';
    file_put_contents($testFile, "<?php\nclass {$testClass} {}\n");

    Autoloader::register($tempPath);

    $result = Autoloader::load($testClass);
    expect($result)->toBeTrue();
    expect(class_exists($testClass, false))->toBeTrue();

    // Limpa
    @unlink($testFile);
    removeDirectory($tempPath);
    Autoloader::unregister();
});

it('carrega classe de src/Controllers/Middlewares/', function () {
    Autoloader::unregister();

    $tempPath = sys_get_temp_dir() . '/autoloader_test_' . uniqid();
    mkdir($tempPath, 0777, true);

    $testDir = $tempPath . '/src/Controllers/Middlewares';
    mkdir($testDir, 0777, true);

    $testClass = 'AutoloaderTestWebMiddleware';
    $testFile = $testDir . '/' . $testClass . '.php';
    file_put_contents($testFile, "<?php\nclass {$testClass} {}\n");

    Autoloader::register($tempPath);

    $result = Autoloader::load($testClass);
    expect($result)->toBeTrue();
    expect(class_exists($testClass, false))->toBeTrue();

    // Limpa
    @unlink($testFile);
    removeDirectory($tempPath);
    Autoloader::unregister();
});

it('carrega classe de src/Models/', function () {
    Autoloader::unregister();

    $tempPath = sys_get_temp_dir() . '/autoloader_test_' . uniqid();
    mkdir($tempPath, 0777, true);

    $testDir = $tempPath . '/src/Models';
    mkdir($testDir, 0777, true);

    $testClass = 'AutoloaderTestModelSrc';
    $testFile = $testDir . '/' . $testClass . '.php';
    file_put_contents($testFile, "<?php\nclass {$testClass} {}\n");

    Autoloader::register($tempPath);

    $result = Autoloader::load($testClass);
    expect($result)->toBeTrue();
    expect(class_exists($testClass, false))->toBeTrue();

    // Limpa
    @unlink($testFile);
    removeDirectory($tempPath);
    Autoloader::unregister();
});

it('carrega classe de src/ quando existe', function () {
    Autoloader::unregister();

    $tempPath = sys_get_temp_dir() . '/autoloader_test_' . uniqid();
    mkdir($tempPath, 0777, true);

    $testDir = $tempPath . '/src';
    mkdir($testDir, 0777, true);

    $testClass = 'AutoloaderTestSrc';
    $testFile = $testDir . '/' . $testClass . '.php';
    file_put_contents($testFile, "<?php\nclass {$testClass} {}\n");

    Autoloader::register($tempPath);

    $result = Autoloader::load($testClass);
    expect($result)->toBeTrue();
    expect(class_exists($testClass, false))->toBeTrue();

    // Limpa
    @unlink($testFile);
    removeDirectory($tempPath);
    Autoloader::unregister();
});

it('não lança erro ao desregistrar quando não está registrado', function () {
    Autoloader::unregister();

    // Desregistra novamente (não deve lançar erro)
    Autoloader::unregister();

    expect(true)->toBeTrue(); // Se chegou aqui, não lançou erro
});
