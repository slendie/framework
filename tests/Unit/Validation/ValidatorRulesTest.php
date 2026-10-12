<?php

declare(strict_types=1);

use Slendie\Tests\Fixtures\Validation\ValidationHelpers as V;

/*
| Uma linha por regra: valores que têm de passar e valores que têm de falhar.
*/
dataset('rules', [
    'required' => ['required', ['a', 0, '0', [1], true, false], ['', '   ', null, []]],
    'filled' => ['filled', ['a', 0], ['', null, []]],
    'accepted' => ['accepted', ['yes', 'on', 1, '1', true, 'true'], ['no', 'off', 0, '0', false, 'false', 'maybe', '']],
    'declined' => ['declined', ['no', 'off', 0, '0', false, 'false'], ['yes', 'on', 1, '1', true, 'true', 'maybe']],
    'string' => ['string', ['a', 'ção'], [1, [], true, 1.5]],
    'numeric' => ['numeric', [1, '1.5', -2, '0', 0.5], ['abc', [], true, '1,5']],
    'integer' => ['integer', [1, '12', '-3', 0], [1.5, '1.5', 'abc', true, []]],
    'boolean' => ['boolean', [true, false, 0, 1, '0', '1'], ['yes', 'true', 2, 'a']],
    'array' => ['array', [[], [1], ['a' => 1]], ['a', 1, true]],
    'array:a,b' => ['array:a,b', [['a' => 1], ['b' => 2, 'a' => 1]], [['c' => 1], ['a' => 1, 'c' => 2]]],
    'json' => ['json', ['{"a":1}', '[]', '1', 'true'], ['{a:1}', 'abc', '{', 1]],
    'date' => ['date', ['2024-02-29', 'tomorrow', '2024-01-05 10:00', '5 January 2024'], ['2023-02-29', 'not-a-date', [], '2024-13-45']],
    'date_format' => ['date_format:Y-m-d', ['2024-02-29', '1999-12-31'], ['29/02/2024', '2023-02-29', '2024-2-9', 'x']],
    'date_format (várias)' => ['date_format:Y-m-d,d/m/Y', ['2024-02-29', '29/02/2024'], ['29-02-2024']],
    'email' => ['email', ['a@b.pt', 'ana.silva+x@example.com', 'x@sub.dominio.org'], ['x', 'a@', '@b.pt', 'a b@c.pt', 'a@b', [], 5]],
    'url' => ['url', ['https://slendie.dev/x?y=1', 'http://localhost', 'ftp://files.example.com/a'], ['slendie.dev', 'javascript:alert(1)', 'mailto:a@b.pt', 'http://', []]],
    'url:https' => ['url:https', ['https://a.pt'], ['http://a.pt', 'ftp://a.pt']],
    'ip' => ['ip', ['127.0.0.1', '::1', '2001:db8::1'], ['999.1.1.1', 'abc', '1.2.3']],
    'ipv4' => ['ipv4', ['127.0.0.1', '10.0.0.255'], ['::1', '1.2.3', '256.1.1.1']],
    'ipv6' => ['ipv6', ['::1', '2001:db8::ff00:42:8329'], ['127.0.0.1', 'abc']],
    'uuid' => ['uuid', ['550e8400-e29b-41d4-a716-446655440000'], ['x', '550e8400e29b41d4a716446655440000', 5]],
    'alpha' => ['alpha', ['abc', 'ção', 'ÁÉÍ'], ['a1', 'a b', '1', 'a-b']],
    'alpha:ascii' => ['alpha:ascii', ['abc'], ['ção']],
    'alpha_num' => ['alpha_num', ['abc1', 'ção2', 12], ['a b', 'a-b', 'a_b']],
    'alpha_dash' => ['alpha_dash', ['a-b_c1', 'ção_1'], ['a b', 'a.b', 'a!']],
    'ascii' => ['ascii', ['abc!123'], ['ção', '€']],
    'lowercase' => ['lowercase', ['abc', 'ção', '123'], ['Abc', 'ÇÃO']],
    'uppercase' => ['uppercase', ['ABC', 'ÇÃO', '123'], ['aBC', 'ção']],
    'regex' => ['regex:/^\d{3}$/', ['123', 456], ['12a', '1234', []]],
    'regex (com vírgula)' => ['regex:/^[a-z]{1,3}$/', ['ab', 'abc'], ['abcd']],
    'not_regex' => ['not_regex:/^\d+$/', ['a1', 'x'], ['12', 345]],
    'starts_with' => ['starts_with:a,b', ['abc', 'bcd'], ['cde', 5, []]],
    'ends_with' => ['ends_with:x,y', ['ax', 'by'], ['xa', 'ab']],
    'doesnt_start_with' => ['doesnt_start_with:a,b', ['cde', 'xa'], ['abc', 'bcd']],
    'doesnt_end_with' => ['doesnt_end_with:x,y', ['xa', 'ab'], ['ax', 'by']],
    'contains' => ['contains:a,b', [['a', 'b', 'c'], ['b', 'a']], [['a'], 'a', []]],
    'in' => ['in:a,b', ['a', 'b', ['a', 'b'], ['a']], ['c', ['a', 'c'], [], 'ab']],
    'not_in' => ['not_in:a,b', ['c', 'ab'], ['a', 'b', ['a', 'b']]],
    'min (string)' => ['min:3', ['abc', 'ação', 'abcd'], ['ab', 'çã']],
    'max (string)' => ['max:3', ['abc', 'ab', 'çãõ'], ['abcd', 'ação']],
    'size (string)' => ['size:3', ['abc', 'çãõ'], ['ab', 'abcd']],
    'between (string)' => ['between:2,4', ['ab', 'abcd'], ['a', 'abcde']],
    'min (numeric)' => ['numeric|min:3', [3, '4', 10.5], [2, '2.9']],
    'max (numeric)' => ['numeric|max:3', [3, '2', -1], [4, '3.1']],
    'size (numeric)' => ['integer|size:3', [3, '3'], [4, '2']],
    'between (numeric)' => ['numeric|between:1,5', [1, 5, '2.5'], [0, '5.1']],
    'min (array)' => ['array|min:2', [[1, 2], [1, 2, 3]], [[], [1]]],
    'max (array)' => ['array|max:2', [[], [1, 2]], [[1, 2, 3]]],
    'size (array)' => ['array|size:2', [[1, 2]], [[1], [1, 2, 3]]],
    'between (array)' => ['array|between:1,2', [[1], [1, 2]], [[], [1, 2, 3]]],
    'digits' => ['digits:3', ['123', 123, '000'], ['12', '1234', '12a', '1.5']],
    'digits_between' => ['digits_between:2,3', ['12', '123'], ['1', '1234', 'ab']],
    'gt (valor)' => ['numeric|gt:5', [6, '5.5'], [5, 4]],
    'gte (valor)' => ['numeric|gte:5', [5, 6], [4, '4.9']],
    'lt (valor)' => ['numeric|lt:5', [4, '4.9'], [5, 6]],
    'lte (valor)' => ['numeric|lte:5', [5, 4], [6, '5.1']],
    'multiple_of' => ['multiple_of:5', [10, '15', 0, -5], [11, 'a']],
    'multiple_of (decimal)' => ['multiple_of:0.5', [1.5, 2, '3.0'], [1.3]],
    'decimal' => ['decimal:2', ['1.50', '10.25', '0.00'], ['1.5', '1', 'abc', '1.505']],
    'decimal (intervalo)' => ['decimal:1,2', ['1.5', '1.55'], ['1', '1.555']],
    'timezone' => ['timezone', ['Europe/Lisbon', 'UTC'], ['Lisbon', 'x', 5]],
    'hex_color' => ['hex_color', ['#fff', '#A1B2C3', '#a1b2c3ff'], ['fff', '#ggg', '#12345']],
    'prohibited' => ['prohibited', ['', null, []], ['a', 0, '0', [1]]],
    'present' => ['present', [null, '', 'a', []], []],
]);

it('regra: valores válidos passam e inválidos falham', function (string $rule, array $valid, array $invalid): void {
    foreach ($valid as $value) {
        expect(V::passes($value, $rule))->toBeTrue('devia passar: '.$rule.' com '.json_encode($value));
    }

    foreach ($invalid as $value) {
        expect(V::passes($value, $rule))->toBeFalse('devia falhar: '.$rule.' com '.json_encode($value));
    }
})->with('rules');

it('regras não implícitas ignoram valores vazios e campos ausentes', function (): void {
    expect(V::make(['f' => ''], ['f' => 'email|min:5|numeric|in:a'])->passes())->toBeTrue()
        ->and(V::make(['f' => '  '], ['f' => 'integer'])->passes())->toBeTrue()
        ->and(V::make([], ['f' => 'email|min:5'])->passes())->toBeTrue()
        ->and(V::make(['f' => null], ['f' => 'string'])->passes())->toBeFalse()
        ->and(V::make(['f' => null], ['f' => 'nullable|string'])->passes())->toBeTrue()
        ->and(V::make([], ['f' => 'required'])->passes())->toBeFalse()
        ->and(V::make([], ['f' => 'present'])->passes())->toBeFalse()
        ->and(V::make([], ['f' => 'prohibited'])->passes())->toBeTrue();
});

// -----------------------------------------------------------------------------
// Regras que dependem de outros campos
// -----------------------------------------------------------------------------

dataset('dependent rules', [
    'required_if (igual)' => [['type' => 'company'], ['name' => 'required_if:type,company'], false],
    'required_if (diferente)' => [['type' => 'person'], ['name' => 'required_if:type,company'], true],
    'required_if (vários valores)' => [['type' => 'b'], ['name' => 'required_if:type,a,b'], false],
    'required_if (preenchido)' => [['type' => 'company', 'name' => 'ACME'], ['name' => 'required_if:type,company'], true],
    'required_if (booleano)' => [['flag' => true], ['name' => 'required_if:flag,true'], false],
    'required_unless (não é)' => [['type' => 'person'], ['name' => 'required_unless:type,company'], false],
    'required_unless (é)' => [['type' => 'company'], ['name' => 'required_unless:type,company'], true],
    'required_with (presente)' => [['a' => 'x'], ['b' => 'required_with:a'], false],
    'required_with (ausente)' => [[], ['b' => 'required_with:a'], true],
    'required_with (vazio)' => [['a' => ''], ['b' => 'required_with:a'], true],
    'required_with (um de vários)' => [['c' => 'x'], ['b' => 'required_with:a,c'], false],
    'required_with_all (todos)' => [['a' => 'x', 'c' => 'y'], ['b' => 'required_with_all:a,c'], false],
    'required_with_all (falta um)' => [['a' => 'x'], ['b' => 'required_with_all:a,c'], true],
    'required_without (falta)' => [[], ['b' => 'required_without:a'], false],
    'required_without (existe)' => [['a' => 'x'], ['b' => 'required_without:a'], true],
    'required_without_all (nenhum)' => [[], ['b' => 'required_without_all:a,c'], false],
    'required_without_all (um existe)' => [['a' => 'x'], ['b' => 'required_without_all:a,c'], true],
    'accepted_if (aceite)' => [['type' => 'x', 'terms' => 'yes'], ['terms' => 'accepted_if:type,x'], true],
    'accepted_if (não aceite)' => [['type' => 'x', 'terms' => 'no'], ['terms' => 'accepted_if:type,x'], false],
    'accepted_if (outra condição)' => [['type' => 'y', 'terms' => 'no'], ['terms' => 'accepted_if:type,x'], true],
    'declined_if' => [['type' => 'x', 'opt' => 'yes'], ['opt' => 'declined_if:type,x'], false],
    'prohibited_if (viola)' => [['type' => 'x', 'f' => 'a'], ['f' => 'prohibited_if:type,x'], false],
    'prohibited_if (não se aplica)' => [['type' => 'y', 'f' => 'a'], ['f' => 'prohibited_if:type,x'], true],
    'prohibited_unless' => [['type' => 'y', 'f' => 'a'], ['f' => 'prohibited_unless:type,x'], false],
    'missing' => [['f' => 'a'], ['f' => 'missing'], false],
    'missing (ausente)' => [[], ['f' => 'missing'], true],
    'missing_if' => [['type' => 'x', 'f' => 'a'], ['f' => 'missing_if:type,x'], false],
    'missing_unless' => [['type' => 'y', 'f' => 'a'], ['f' => 'missing_unless:type,x'], false],
    'missing_with' => [['a' => '1', 'f' => 'a'], ['f' => 'missing_with:a'], false],
    'missing_with_all' => [['a' => '1', 'b' => '2', 'f' => 'a'], ['f' => 'missing_with_all:a,b'], false],
    'same' => [['a' => 'x', 'b' => 'x'], ['b' => 'same:a'], true],
    'same (diferente)' => [['a' => 'x', 'b' => 'y'], ['b' => 'same:a'], false],
    'different' => [['a' => 'x', 'b' => 'y'], ['b' => 'different:a'], true],
    'different (igual)' => [['a' => 'x', 'b' => 'x'], ['b' => 'different:a'], false],
    'confirmed' => [['password' => 'abc', 'password_confirmation' => 'abc'], ['password' => 'confirmed'], true],
    'confirmed (não coincide)' => [['password' => 'abc', 'password_confirmation' => 'abd'], ['password' => 'confirmed'], false],
    'confirmed (ausente)' => [['password' => 'abc'], ['password' => 'confirmed'], false],
    'gt (campo)' => [['a' => 5, 'b' => 3], ['a' => 'numeric|gt:b'], true],
    'gt (campo, falha)' => [['a' => 3, 'b' => 5], ['a' => 'numeric|gt:b'], false],
    'gte (campo)' => [['a' => 5, 'b' => 5], ['a' => 'numeric|gte:b'], true],
    'lt (campo)' => [['a' => 3, 'b' => 5], ['a' => 'numeric|lt:b'], true],
    'lte (campo)' => [['a' => 6, 'b' => 5], ['a' => 'numeric|lte:b'], false],
    'gt (strings por tamanho)' => [['a' => 'abcd', 'b' => 'abc'], ['a' => 'gt:b'], true],
    'before (data fixa)' => [['d' => '2024-01-01'], ['d' => 'date|before:2024-06-01'], true],
    'before (falha)' => [['d' => '2024-07-01'], ['d' => 'date|before:2024-06-01'], false],
    'before (campo)' => [['start' => '2024-01-01', 'end' => '2024-02-01'], ['start' => 'before:end'], true],
    'after (campo)' => [['start' => '2024-03-01', 'end' => '2024-02-01'], ['start' => 'after:end'], true],
    'after (campo, falha)' => [['start' => '2024-01-01', 'end' => '2024-02-01'], ['start' => 'after:end'], false],
    'before_or_equal (igual)' => [['start' => '2024-02-01', 'end' => '2024-02-01'], ['start' => 'before_or_equal:end'], true],
    'after_or_equal (igual)' => [['start' => '2024-02-01', 'end' => '2024-02-01'], ['start' => 'after_or_equal:end'], true],
    'after (today)' => [['d' => '2999-01-01'], ['d' => 'after:today'], true],
    'before (formato)' => [['d' => '31/01/2024'], ['d' => 'date_format:d/m/Y|before:2024-02-15'], true],
    'in_array' => [['f' => 'a', 'list' => ['a', 'b']], ['f' => 'in_array:list'], true],
    'in_array (falha)' => [['f' => 'z', 'list' => ['a', 'b']], ['f' => 'in_array:list'], false],
    'in_array (wildcard)' => [['f' => 'b', 'list' => [['v' => 'a'], ['v' => 'b']]], ['f' => 'in_array:list.*.v'], true],
    'distinct' => [['tags' => ['a', 'b', 'c']], ['tags.*' => 'distinct'], true],
    'distinct (repetido)' => [['tags' => ['a', 'b', 'a']], ['tags.*' => 'distinct'], false],
    'distinct (ignore_case)' => [['tags' => ['a', 'A']], ['tags.*' => 'distinct:ignore_case'], false],
    'distinct (case sensitive)' => [['tags' => ['a', 'A']], ['tags.*' => 'distinct'], true],
]);

it('regra com dependência de outros campos', function (array $data, array $rules, bool $passes): void {
    expect(V::make($data, $rules)->passes())->toBe($passes);
})->with('dependent rules');

// -----------------------------------------------------------------------------
// Mensagens em pt e en
// -----------------------------------------------------------------------------

dataset('messages', [
    ['required', ['f' => ''], 'O campo f é obrigatório.', 'The f field is required.'],
    ['email', ['f' => 'x'], 'O campo f tem de ser um endereço de e-mail válido.', 'The f field must be a valid email address.'],
    ['string', ['f' => 1], 'O campo f tem de ser texto.', 'The f field must be a string.'],
    ['numeric', ['f' => 'a'], 'O campo f tem de ser um número.', 'The f field must be a number.'],
    ['integer', ['f' => '1.5'], 'O campo f tem de ser um número inteiro.', 'The f field must be an integer.'],
    ['boolean', ['f' => 'x'], 'O campo f tem de ser verdadeiro ou falso.', 'The f field must be true or false.'],
    ['array', ['f' => 'x'], 'O campo f tem de ser uma lista.', 'The f field must be an array.'],
    ['json', ['f' => 'x'], 'O campo f tem de ser um texto JSON válido.', 'The f field must be a valid JSON string.'],
    ['date', ['f' => 'not-a-date'], 'O campo f tem de ser uma data válida.', 'The f field must be a valid date.'],
    ['date_format:Y-m-d', ['f' => 'x'], 'O campo f tem de respeitar o formato Y-m-d.', 'The f field must match the format Y-m-d.'],
    ['url', ['f' => 'x'], 'O campo f tem de ser um URL válido.', 'The f field must be a valid URL.'],
    ['ip', ['f' => 'x'], 'O campo f tem de ser um endereço IP válido.', 'The f field must be a valid IP address.'],
    ['uuid', ['f' => 'x'], 'O campo f tem de ser um UUID válido.', 'The f field must be a valid UUID.'],
    ['alpha', ['f' => '1'], 'O campo f só pode conter letras.', 'The f field must only contain letters.'],
    ['in:a,b', ['f' => 'c'], 'O valor escolhido para f é inválido.', 'The selected f is invalid.'],
    ['not_in:a,b', ['f' => 'a'], 'O valor escolhido para f é inválido.', 'The selected f is invalid.'],
    ['starts_with:a,b', ['f' => 'c'], 'O campo f tem de começar com um de: a, b.', 'The f field must start with one of the following: a, b.'],
    ['min:3', ['f' => 'ab'], 'O campo f tem de ter pelo menos 3 caracteres.', 'The f field must be at least 3 characters.'],
    ['max:3', ['f' => 'abcd'], 'O campo f não pode ter mais de 3 caracteres.', 'The f field must not be greater than 3 characters.'],
    ['size:3', ['f' => 'ab'], 'O campo f tem de ter 3 caracteres.', 'The f field must be 3 characters.'],
    ['between:2,4', ['f' => 'a'], 'O campo f tem de ter entre 2 e 4 caracteres.', 'The f field must be between 2 and 4 characters.'],
    ['numeric|min:3', ['f' => 1], 'O campo f tem de ser pelo menos 3.', 'The f field must be at least 3.'],
    ['numeric|max:3', ['f' => 5], 'O campo f não pode ser superior a 3.', 'The f field must not be greater than 3.'],
    ['array|between:2,3', ['f' => [1]], 'O campo f tem de ter entre 2 e 3 itens.', 'The f field must have between 2 and 3 items.'],
    ['array|min:2', ['f' => [1]], 'O campo f tem de ter pelo menos 2 itens.', 'The f field must have at least 2 items.'],
    ['digits:3', ['f' => '12'], 'O campo f tem de ter 3 dígitos.', 'The f field must be 3 digits.'],
    ['digits_between:2,3', ['f' => '1'], 'O campo f tem de ter entre 2 e 3 dígitos.', 'The f field must be between 2 and 3 digits.'],
    ['multiple_of:5', ['f' => 3], 'O campo f tem de ser múltiplo de 5.', 'The f field must be a multiple of 5.'],
    ['decimal:2', ['f' => '1'], 'O campo f tem de ter 2 casas decimais.', 'The f field must have 2 decimal places.'],
    ['accepted', ['f' => 'no'], 'O campo f tem de ser aceite.', 'The f field must be accepted.'],
    ['declined', ['f' => 'yes'], 'O campo f tem de ser recusado.', 'The f field must be declined.'],
    ['prohibited', ['f' => 'a'], 'O campo f não é permitido.', 'The f field is prohibited.'],
    ['present', [], 'O campo f tem de estar presente.', 'The f field must be present.'],
    ['filled', ['f' => []], 'O campo f tem de ter um valor.', 'The f field must have a value.'],
    ['lowercase', ['f' => 'A'], 'O campo f tem de estar em minúsculas.', 'The f field must be lowercase.'],
    ['required_if:o,x', ['f' => '', 'o' => 'x'], 'O campo f é obrigatório quando o é x.', 'The f field is required when o is x.'],
    ['required_unless:o,x,y', ['f' => '', 'o' => 'z'], 'O campo f é obrigatório a menos que o esteja em x, y.', 'The f field is required unless o is in x, y.'],
    ['required_with:o,p', ['f' => '', 'o' => '1'], 'O campo f é obrigatório quando o / p está presente.', 'The f field is required when o / p is present.'],
    ['required_without:o', ['f' => ''], 'O campo f é obrigatório quando o não está presente.', 'The f field is required when o is not present.'],
    ['same:o', ['f' => 'a', 'o' => 'b'], 'O campo f tem de coincidir com o.', 'The f field must match o.'],
    ['different:o', ['f' => 'a', 'o' => 'a'], 'Os campos f e o têm de ser diferentes.', 'The f field and o must be different.'],
    ['confirmed', ['f' => 'a', 'f_confirmation' => 'b'], 'A confirmação do campo f não coincide.', 'The f field confirmation does not match.'],
    ['before:2020-01-01', ['f' => '2021-01-01'], 'O campo f tem de ser uma data anterior a 2020-01-01.', 'The f field must be a date before 2020-01-01.'],
    ['after:o', ['f' => '2020-01-01', 'o' => '2021-01-01'], 'O campo f tem de ser uma data posterior a o.', 'The f field must be a date after o.'],
    ['numeric|gt:o', ['f' => 1, 'o' => 5], 'O campo f tem de ser maior que 5.', 'The f field must be greater than 5.'],
    ['in_array:o', ['f' => 'a', 'o' => ['b']], 'O campo f tem de existir em o.', 'The f field must exist in o.'],
    ['mimes:jpg,png', ['f' => 'x'], 'O campo f tem de ser um ficheiro do tipo: jpg, png.', 'The f field must be a file of type: jpg, png.'],
    ['image', ['f' => 'x'], 'O campo f tem de ser uma imagem.', 'The f field must be an image.'],
    ['file', ['f' => 'x'], 'O campo f tem de ser um ficheiro.', 'The f field must be a file.'],
]);

it('mensagens em pt e en', function (string $rule, array $data, string $pt, string $en): void {
    $first = static fn (string $locale): string => V::make($data, ['f' => $rule], $locale)->errors()->first('f');

    expect($first('pt'))->toBe($pt)
        ->and($first('en'))->toBe($en);
})->with('messages');

it('regras inexistentes ou não suportadas lançam uma excepção clara', function (string $rule): void {
    V::make(['f' => 'x'], ['f' => $rule])->passes();
})->with(['active_url', 'dimensions:min_width=100', 'uncompromised', 'nao_existe'])
    ->throws(BadMethodCallException::class);

it('regras sem parâmetros obrigatórios lançam InvalidArgumentException', function (): void {
    V::make(['f' => 'x'], ['f' => 'min'])->passes();
})->throws(InvalidArgumentException::class);
