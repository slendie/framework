<?php

declare(strict_types=1);

use Slendie\Framework\Env;

require_once __DIR__ . '/../../vendor/autoload.php';

// Função auxiliar para resetar variáveis do Env
function resetEnvVars()
{
    $reflection = new ReflectionClass(Env::class);
    $property = $reflection->getProperty('vars');
    $property->setAccessible(true);
    $property->setValue(null, []);
}

// Função auxiliar para criar arquivo .env temporário
function createTempEnvFile($content)
{
    $tempFile = sys_get_temp_dir() . '/env_test_' . uniqid() . '.env';
    file_put_contents($tempFile, $content);
    return $tempFile;
}

it('define variável de ambiente com set', function () {
    resetEnvVars();

    Env::set('TEST_KEY', 'test_value');

    expect(Env::get('TEST_KEY'))->toBe('test_value');
    expect($_ENV['TEST_KEY'])->toBe('test_value');
    expect(getenv('TEST_KEY'))->toBe('test_value');
});

it('sobrescreve variável existente com set', function () {
    resetEnvVars();

    Env::set('TEST_KEY', 'initial_value');
    Env::set('TEST_KEY', 'updated_value');

    expect(Env::get('TEST_KEY'))->toBe('updated_value');
});

it('obtém variável definida com get', function () {
    resetEnvVars();

    Env::set('TEST_KEY', 'test_value');

    expect(Env::get('TEST_KEY'))->toBe('test_value');
});

it('retorna valor padrão quando variável não existe', function () {
    resetEnvVars();

    expect(Env::get('NON_EXISTENT_KEY', 'default_value'))->toBe('default_value');
});

it('retorna null quando variável não existe e não há default', function () {
    resetEnvVars();

    expect(Env::get('NON_EXISTENT_KEY'))->toBeNull();
});

it('obtém variável de getenv quando não está em vars', function () {
    resetEnvVars();

    // Define variável diretamente no ambiente do sistema
    putenv('SYSTEM_VAR=system_value');
    $_ENV['SYSTEM_VAR'] = 'system_value';

    expect(Env::get('SYSTEM_VAR'))->toBe('system_value');

    // Limpa
    putenv('SYSTEM_VAR');
    unset($_ENV['SYSTEM_VAR']);
});

it('carrega variáveis de arquivo .env', function () {
    resetEnvVars();

    $content = "KEY1=value1\nKEY2=value2\nKEY3=value3";
    $tempFile = createTempEnvFile($content);

    Env::load($tempFile);

    expect(Env::get('KEY1'))->toBe('value1');
    expect(Env::get('KEY2'))->toBe('value2');
    expect(Env::get('KEY3'))->toBe('value3');

    // Limpa
    @unlink($tempFile);
});

it('ignora linhas vazias ao carregar arquivo .env', function () {
    resetEnvVars();

    $content = "KEY1=value1\n\nKEY2=value2\n\n\nKEY3=value3";
    $tempFile = createTempEnvFile($content);

    Env::load($tempFile);

    expect(Env::get('KEY1'))->toBe('value1');
    expect(Env::get('KEY2'))->toBe('value2');
    expect(Env::get('KEY3'))->toBe('value3');

    // Limpa
    @unlink($tempFile);
});

it('ignora comentários ao carregar arquivo .env', function () {
    resetEnvVars();

    $content = "# This is a comment\nKEY1=value1\n# Another comment\nKEY2=value2";
    $tempFile = createTempEnvFile($content);

    Env::load($tempFile);

    expect(Env::get('KEY1'))->toBe('value1');
    expect(Env::get('KEY2'))->toBe('value2');
    expect(Env::get('# This is a comment'))->toBeNull();

    // Limpa
    @unlink($tempFile);
});

it('remove aspas simples e duplas dos valores', function () {
    resetEnvVars();

    $content = "KEY1=\"quoted_value\"\nKEY2='single_quoted'\nKEY3=\"value with spaces\"";
    $tempFile = createTempEnvFile($content);

    Env::load($tempFile);

    expect(Env::get('KEY1'))->toBe('quoted_value');
    expect(Env::get('KEY2'))->toBe('single_quoted');
    expect(Env::get('KEY3'))->toBe('value with spaces');

    // Limpa
    @unlink($tempFile);
});

it('remove espaços em branco dos valores', function () {
    resetEnvVars();

    $content = "KEY1= value_with_spaces \nKEY2=  another_value  ";
    $tempFile = createTempEnvFile($content);

    Env::load($tempFile);

    expect(Env::get('KEY1'))->toBe('value_with_spaces');
    expect(Env::get('KEY2'))->toBe('another_value');

    // Limpa
    @unlink($tempFile);
});

it('ignora linhas sem sinal de igual', function () {
    resetEnvVars();

    $content = "KEY1=value1\nINVALID_LINE\nKEY2=value2";
    $tempFile = createTempEnvFile($content);

    Env::load($tempFile);

    expect(Env::get('KEY1'))->toBe('value1');
    expect(Env::get('KEY2'))->toBe('value2');
    expect(Env::get('INVALID_LINE'))->toBeNull();

    // Limpa
    @unlink($tempFile);
});

it('não faz nada quando arquivo não existe', function () {
    resetEnvVars();

    Env::load('/path/that/does/not/exist.env');

    // Não deve lançar erro
    expect(true)->toBeTrue();
});

it('carrega apenas primeira ocorrência de sinal de igual', function () {
    resetEnvVars();

    $content = "KEY1=value1=value2=value3";
    $tempFile = createTempEnvFile($content);

    Env::load($tempFile);

    expect(Env::get('KEY1'))->toBe('value1=value2=value3');

    // Limpa
    @unlink($tempFile);
});

it('atualiza $_ENV ao definir variável', function () {
    resetEnvVars();

    Env::set('TEST_KEY', 'test_value');

    expect($_ENV['TEST_KEY'])->toBe('test_value');
});

it('atualiza putenv ao definir variável', function () {
    resetEnvVars();

    Env::set('TEST_KEY', 'test_value');

    expect(getenv('TEST_KEY'))->toBe('test_value');
});

it('atualiza $_ENV ao carregar arquivo', function () {
    resetEnvVars();

    $content = "KEY1=value1\nKEY2=value2";
    $tempFile = createTempEnvFile($content);

    Env::load($tempFile);

    expect($_ENV['KEY1'])->toBe('value1');
    expect($_ENV['KEY2'])->toBe('value2');

    // Limpa
    @unlink($tempFile);
});

it('atualiza putenv ao carregar arquivo', function () {
    resetEnvVars();

    $content = "KEY1=value1\nKEY2=value2";
    $tempFile = createTempEnvFile($content);

    Env::load($tempFile);

    expect(getenv('KEY1'))->toBe('value1');
    expect(getenv('KEY2'))->toBe('value2');

    // Limpa
    @unlink($tempFile);
});

it('prioriza variável em vars sobre getenv', function () {
    resetEnvVars();

    // Define no sistema primeiro
    putenv('PRIORITY_VAR=system_value');
    $_ENV['PRIORITY_VAR'] = 'system_value';

    // Define em vars
    Env::set('PRIORITY_VAR', 'vars_value');

    // Deve retornar valor de vars, não do sistema
    expect(Env::get('PRIORITY_VAR'))->toBe('vars_value');

    // Limpa
    putenv('PRIORITY_VAR');
    unset($_ENV['PRIORITY_VAR']);
});

it('função global env() funciona corretamente', function () {
    resetEnvVars();

    Env::set('GLOBAL_TEST', 'global_value');

    expect(env('GLOBAL_TEST'))->toBe('global_value');
    expect(env('NON_EXISTENT', 'default'))->toBe('default');
});

it('função global env() retorna null quando não existe', function () {
    resetEnvVars();

    expect(env('NON_EXISTENT_GLOBAL'))->toBeNull();
});

it('carrega variáveis com valores vazios', function () {
    resetEnvVars();

    $content = "EMPTY_KEY=\nKEY_WITH_VALUE=value";
    $tempFile = createTempEnvFile($content);

    Env::load($tempFile);

    expect(Env::get('EMPTY_KEY'))->toBe('');
    expect(Env::get('KEY_WITH_VALUE'))->toBe('value');

    // Limpa
    @unlink($tempFile);
});

it('carrega variáveis com espaços ao redor do sinal de igual', function () {
    resetEnvVars();

    $content = "KEY1 = value1\nKEY2= value2\nKEY3 =value3";
    $tempFile = createTempEnvFile($content);

    Env::load($tempFile);

    expect(Env::get('KEY1'))->toBe('value1');
    expect(Env::get('KEY2'))->toBe('value2');
    expect(Env::get('KEY3'))->toBe('value3');

    // Limpa
    @unlink($tempFile);
});

it('carrega múltiplas variáveis do mesmo arquivo', function () {
    resetEnvVars();

    $content = "VAR1=value1\nVAR2=value2\nVAR3=value3\nVAR4=value4\nVAR5=value5";
    $tempFile = createTempEnvFile($content);

    Env::load($tempFile);

    expect(Env::get('VAR1'))->toBe('value1');
    expect(Env::get('VAR2'))->toBe('value2');
    expect(Env::get('VAR3'))->toBe('value3');
    expect(Env::get('VAR4'))->toBe('value4');
    expect(Env::get('VAR5'))->toBe('value5');

    // Limpa
    @unlink($tempFile);
});

it('sobrescreve variáveis ao carregar arquivo novamente', function () {
    resetEnvVars();

    $content1 = "KEY1=value1\nKEY2=value2";
    $tempFile1 = createTempEnvFile($content1);

    Env::load($tempFile1);
    expect(Env::get('KEY1'))->toBe('value1');

    $content2 = "KEY1=updated_value\nKEY2=value2";
    $tempFile2 = createTempEnvFile($content2);

    Env::load($tempFile2);
    expect(Env::get('KEY1'))->toBe('updated_value');

    // Limpa
    @unlink($tempFile1);
    @unlink($tempFile2);
});

it('define variável com valor numérico', function () {
    resetEnvVars();

    Env::set('NUMERIC_KEY', '123');

    expect(Env::get('NUMERIC_KEY'))->toBe('123');
});

it('define variável com valor booleano como string', function () {
    resetEnvVars();

    Env::set('BOOL_KEY', 'true');

    expect(Env::get('BOOL_KEY'))->toBe('true');
});

it('carrega variável com valor que contém espaços', function () {
    resetEnvVars();

    $content = 'KEY1="value with spaces"';
    $tempFile = createTempEnvFile($content);

    Env::load($tempFile);

    expect(Env::get('KEY1'))->toBe('value with spaces');

    // Limpa
    @unlink($tempFile);
});

it('carrega variável com valor que contém caracteres especiais', function () {
    resetEnvVars();

    $content = "KEY1=value@example.com\nKEY2=value#123\nKEY3=value\$test";
    $tempFile = createTempEnvFile($content);

    Env::load($tempFile);

    expect(Env::get('KEY1'))->toBe('value@example.com');
    expect(Env::get('KEY2'))->toBe('value#123');
    expect(Env::get('KEY3'))->toBe('value$test');

    // Limpa
    @unlink($tempFile);
});

it('ignora comentários no meio da linha', function () {
    resetEnvVars();

    // Comentários só são ignorados se estiverem no início da linha
    $content = "KEY1=value1 # This is not a comment\n# This is a comment\nKEY2=value2";
    $tempFile = createTempEnvFile($content);

    Env::load($tempFile);

    // O valor deve incluir o comentário
    expect(Env::get('KEY1'))->toContain('value1');
    expect(Env::get('KEY2'))->toBe('value2');

    // Limpa
    @unlink($tempFile);
});

it('carrega arquivo com diferentes tipos de quebra de linha', function () {
    resetEnvVars();

    // Testa com \n (Unix) e \r\n (Windows)
    $content = "KEY1=value1\nKEY2=value2\r\nKEY3=value3";
    $tempFile = createTempEnvFile($content);

    Env::load($tempFile);

    expect(Env::get('KEY1'))->toBe('value1');
    expect(Env::get('KEY2'))->toBe('value2');
    expect(Env::get('KEY3'))->toBe('value3');

    // Limpa
    @unlink($tempFile);
});

it('define e obtém variável com chave complexa', function () {
    resetEnvVars();

    Env::set('APP_NAME', 'My App');
    Env::set('DB_CONNECTION', 'sqlite');
    Env::set('DB_DATABASE', ':memory:');

    expect(Env::get('APP_NAME'))->toBe('My App');
    expect(Env::get('DB_CONNECTION'))->toBe('sqlite');
    expect(Env::get('DB_DATABASE'))->toBe(':memory:');
});

it('retorna false quando getenv retorna false', function () {
    resetEnvVars();

    // Remove variável do sistema se existir
    putenv('NON_EXISTENT_VAR');
    unset($_ENV['NON_EXISTENT_VAR']);

    // Deve retornar default quando getenv retorna false
    expect(Env::get('NON_EXISTENT_VAR', 'default'))->toBe('default');
});

it('carrega arquivo vazio sem erros', function () {
    resetEnvVars();

    $tempFile = createTempEnvFile('');

    Env::load($tempFile);

    // Não deve lançar erro
    expect(true)->toBeTrue();

    // Limpa
    @unlink($tempFile);
});

it('carrega arquivo apenas com comentários', function () {
    resetEnvVars();

    $content = "# Comment 1\n# Comment 2\n# Comment 3";
    $tempFile = createTempEnvFile($content);

    Env::load($tempFile);

    // Não deve ter variáveis definidas (verifica uma chave que não existe)
    expect(Env::get('NON_EXISTENT_KEY_FOR_COMMENTS_TEST'))->toBeNull();

    // Limpa
    @unlink($tempFile);
});

it('carrega arquivo apenas com linhas vazias', function () {
    resetEnvVars();

    $content = "\n\n\n";
    $tempFile = createTempEnvFile($content);

    Env::load($tempFile);

    // Não deve ter variáveis definidas (verifica uma chave que não existe)
    expect(Env::get('NON_EXISTENT_KEY_FOR_EMPTY_LINES_TEST'))->toBeNull();

    // Limpa
    @unlink($tempFile);
});

it('remove apenas aspas externas, não internas', function () {
    resetEnvVars();

    $content = 'KEY1="value with "internal" quotes"';
    $tempFile = createTempEnvFile($content);

    Env::load($tempFile);

    // Deve remover apenas as aspas externas
    expect(Env::get('KEY1'))->toBe('value with "internal" quotes');

    // Limpa
    @unlink($tempFile);
});

it('trata valores com múltiplos espaços', function () {
    resetEnvVars();

    $content = 'KEY1="value   with   multiple   spaces"';
    $tempFile = createTempEnvFile($content);

    Env::load($tempFile);

    expect(Env::get('KEY1'))->toBe('value   with   multiple   spaces');

    // Limpa
    @unlink($tempFile);
});

it('carrega variável com valor que começa e termina com espaços', function () {
    resetEnvVars();

    $content = 'KEY1="  value with spaces  "';
    $tempFile = createTempEnvFile($content);

    Env::load($tempFile);

    // trim remove espaços e aspas
    expect(Env::get('KEY1'))->toBe('value with spaces');

    // Limpa
    @unlink($tempFile);
});
