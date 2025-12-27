<?php

declare(strict_types=1);

use Slendie\Framework\CSRF;

require_once __DIR__ . '/../../vendor/autoload.php';

it('gera token CSRF e armazena na sessão', function () {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Limpa o token anterior
    unset($_SESSION['_csrf_token']);

    // Gera um novo token
    $token = CSRF::token();

    expect($token)->toBeString();
    expect(mb_strlen($token))->toBe(64); // 32 bytes em hex = 64 caracteres
    expect($_SESSION['_csrf_token'])->toBe($token);
});

it('retorna o mesmo token quando já existe na sessão', function () {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Define um token inicial
    $initialToken = bin2hex(random_bytes(32));
    $_SESSION['_csrf_token'] = $initialToken;

    // Chama token() novamente
    $token = CSRF::token();

    expect($token)->toBe($initialToken);
    expect($_SESSION['_csrf_token'])->toBe($initialToken);
});

it('gera campo HTML com token CSRF', function () {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Limpa o token anterior
    unset($_SESSION['_csrf_token']);

    // Gera o campo
    $field = CSRF::field();

    expect($field)->toBeString();
    expect($field)->toContain('<input');
    expect($field)->toContain('type="hidden"');
    expect($field)->toContain('name="_token"');
    expect($field)->toContain('value="');

    // Verifica se o token foi gerado
    expect(isset($_SESSION['_csrf_token']))->toBeTrue();
});

it('gera campo HTML com token escapado corretamente', function () {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Define um token com caracteres especiais para testar escape
    $testToken = 'test"token\'value<>';
    $_SESSION['_csrf_token'] = $testToken;

    $field = CSRF::field();

    // Verifica que o HTML está escapado corretamente
    // O token com aspas não deve aparecer literalmente no HTML
    expect($field)->not->toContain('test"token');
    expect($field)->not->toContain('test\'token');
    // O valor deve estar escapado no HTML (aspas devem ser &quot;)
    expect($field)->toContain('value="');
    // Verifica que caracteres especiais foram escapados
    expect($field)->toContain('&quot;');
});

it('valida token CSRF válido', function () {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Gera um token
    $token = CSRF::token();

    // Valida o token
    $isValid = CSRF::validate($token);

    expect($isValid)->toBeTrue();
});

it('valida token CSRF inválido', function () {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Gera um token
    CSRF::token();

    // Tenta validar com token diferente
    $invalidToken = bin2hex(random_bytes(32));
    $isValid = CSRF::validate($invalidToken);

    expect($isValid)->toBeFalse();
});

it('valida token CSRF vazio', function () {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Gera um token
    CSRF::token();

    // Tenta validar com token vazio
    $isValid = CSRF::validate('');

    expect($isValid)->toBeFalse();
});

it('valida token CSRF null', function () {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Gera um token
    CSRF::token();

    // Tenta validar com null
    $isValid = CSRF::validate(null);

    expect($isValid)->toBeFalse();
});

it('valida token CSRF lendo de $_POST quando token não é fornecido', function () {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Gera um token
    $token = CSRF::token();

    // Simula POST com token
    $_POST['_token'] = $token;

    // Valida sem passar token (deve ler de $_POST)
    $isValid = CSRF::validate();

    expect($isValid)->toBeTrue();

    // Limpa POST
    unset($_POST['_token']);
});

it('valida token CSRF inválido de $_POST', function () {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Gera um token
    CSRF::token();

    // Simula POST com token inválido
    $_POST['_token'] = 'invalid_token';

    // Valida sem passar token (deve ler de $_POST)
    $isValid = CSRF::validate();

    expect($isValid)->toBeFalse();

    // Limpa POST
    unset($_POST['_token']);
});

it('valida retorna false quando não há token na sessão', function () {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Remove token da sessão
    unset($_SESSION['_csrf_token']);

    // Tenta validar
    $isValid = CSRF::validate('any_token');

    expect($isValid)->toBeFalse();
});

it('regenera token CSRF', function () {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Gera token inicial
    $initialToken = CSRF::token();

    // Regenera o token
    $newToken = CSRF::regenerate();

    expect($newToken)->toBeString();
    expect(mb_strlen($newToken))->toBe(64);
    expect($newToken)->not->toBe($initialToken);
    expect($_SESSION['_csrf_token'])->toBe($newToken);
});

it('regenera token múltiplas vezes gera tokens diferentes', function () {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Gera token inicial
    $token1 = CSRF::regenerate();
    $token2 = CSRF::regenerate();
    $token3 = CSRF::regenerate();

    expect($token1)->not->toBe($token2);
    expect($token2)->not->toBe($token3);
    expect($token1)->not->toBe($token3);

    // Todos devem ter 64 caracteres
    expect(mb_strlen($token1))->toBe(64);
    expect(mb_strlen($token2))->toBe(64);
    expect(mb_strlen($token3))->toBe(64);
});

it('valida token após regeneração', function () {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Gera token inicial
    $initialToken = CSRF::token();

    // Valida token inicial
    expect(CSRF::validate($initialToken))->toBeTrue();

    // Regenera token
    $newToken = CSRF::regenerate();

    // Token antigo não deve mais ser válido
    expect(CSRF::validate($initialToken))->toBeFalse();

    // Novo token deve ser válido
    expect(CSRF::validate($newToken))->toBeTrue();
});

it('gera tokens únicos em chamadas diferentes', function () {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Limpa sessão
    unset($_SESSION['_csrf_token']);

    // Gera primeiro token
    $token1 = CSRF::token();

    // Limpa sessão novamente
    unset($_SESSION['_csrf_token']);

    // Gera segundo token
    $token2 = CSRF::token();

    // Tokens devem ser diferentes (muito improvável serem iguais)
    expect($token1)->not->toBe($token2);
});

it('field() sempre retorna HTML válido', function () {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Limpa token
    unset($_SESSION['_csrf_token']);

    // Gera campo múltiplas vezes
    $field1 = CSRF::field();
    $field2 = CSRF::field();

    // Ambos devem conter o mesmo token (já que não foi regenerado)
    expect($field1)->toContain($_SESSION['_csrf_token']);
    expect($field2)->toContain($_SESSION['_csrf_token']);

    // Ambos devem ter estrutura HTML válida
    expect($field1)->toContain('<input');
    expect($field2)->toContain('<input');
});

it('valida com hash_equals para prevenir timing attacks', function () {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Gera token
    $token = CSRF::token();

    // Verifica o últimmo caracter do $token
    $lastCharacter = mb_substr($token, -1);

    // Cria token similar mas diferente (um caractere diferente)
    if ($lastCharacter === '0') {
        $similarToken = mb_substr($token, 0, -1) . '5';
    } else {
        $similarToken = mb_substr($token, 0, -1) . '0';
    }

    // Validação deve retornar false mesmo com token similar
    expect(CSRF::validate($similarToken))->toBeFalse();
    expect(CSRF::validate($token))->toBeTrue();
});

it('valida token quando $_POST tem token mas sessão não tem', function () {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Remove token da sessão
    unset($_SESSION['_csrf_token']);

    // Define token em POST
    $_POST['_token'] = 'some_token';

    // Validação deve retornar false
    $isValid = CSRF::validate();

    expect($isValid)->toBeFalse();

    // Limpa POST
    unset($_POST['_token']);
});

it('valida token quando $_POST não tem _token', function () {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Gera token na sessão
    CSRF::token();

    // Remove _token de POST
    unset($_POST['_token']);

    // Validação sem parâmetro deve retornar false
    $isValid = CSRF::validate();

    expect($isValid)->toBeFalse();
});

it('token() cria novo token se sessão foi limpa', function () {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Gera token inicial
    $token1 = CSRF::token();

    // Limpa sessão
    unset($_SESSION['_csrf_token']);

    // Gera novo token
    $token2 = CSRF::token();

    // Deve ser um token diferente
    expect($token2)->not->toBe($token1);
    expect(isset($_SESSION['_csrf_token']))->toBeTrue();
});

it('field() gera HTML com atributos corretos', function () {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Limpa token
    unset($_SESSION['_csrf_token']);

    $field = CSRF::field();

    // Verifica estrutura HTML
    expect($field)->toStartWith('<input');
    expect($field)->toEndWith('>');
    expect($field)->toContain('type="hidden"');
    expect($field)->toContain('name="_token"');

    // Verifica que tem um value
    preg_match('/value="([^"]+)"/', $field, $matches);
    expect(isset($matches[1]))->toBeTrue();
    expect(mb_strlen($matches[1]))->toBe(64);
});
