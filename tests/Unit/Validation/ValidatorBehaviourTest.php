<?php

declare(strict_types=1);

use Slendie\Tests\Fixtures\Database\Status;
use Slendie\Tests\Fixtures\Validation\ValidationHelpers as V;
use Slendie\Validation\Rule;
use Slendie\Validation\Rules\Password;
use Slendie\Validation\ValidationException;
use Slendie\Validation\Validator;

it('regras em string, em array, objectos e closures', function (): void {
    $closure = function (string $attribute, mixed $value, Closure $fail): void {
        if ($value !== 'ok') {
            $fail('O :attribute tem de ser "ok" (recebi :input).');
        }
    };

    $v = V::make(
        ['a' => '', 'b' => 'x', 'c' => 'nope', 'd' => 'zz'],
        ['a' => ['required'], 'b' => ['required', 'email'], 'c' => ['required', $closure], 'd' => 'required|max:1'],
    );

    expect($v->fails())->toBeTrue()
        ->and($v->errors()->keys())->toBe(['a', 'b', 'c', 'd'])
        ->and($v->errors()->first('c'))->toBe('O c tem de ser "ok" (recebi nope).');
});

it('uma closure só corre com valor presente e não vazio', function (): void {
    $calls = 0;
    $closure = function () use (&$calls): void {
        $calls++;
    };

    V::make(['a' => '', 'b' => null], ['a' => [$closure], 'b' => [$closure], 'c' => [$closure]])->passes();

    expect($calls)->toBe(1);
});

it('atributos aninhados com dot e wildcards', function (): void {
    $v = V::make(
        ['address' => ['city' => ''], 'items' => [['qty' => 1], ['qty' => 'x'], ['qty' => 5]]],
        ['address.city' => 'required', 'items.*.qty' => 'integer|min:2'],
    );

    expect($v->fails())->toBeTrue()
        ->and($v->errors()->keys())->toBe(['address.city', 'items.0.qty', 'items.1.qty'])
        ->and($v->errors()->has('items.*.qty'))->toBeTrue()
        ->and($v->errors()->first('items.0.qty'))->toContain('pelo menos 2');
});

it('wildcards em dois níveis e wildcards sem dados não geram erros', function (): void {
    $v = V::make(
        ['users' => [['tags' => ['a', '']], ['tags' => ['b']]]],
        ['users.*.tags.*' => 'required|string'],
    );

    expect($v->errors()->keys())->toBe(['users.0.tags.1'])
        ->and(V::make(['items' => []], ['items.*.qty' => 'required'])->passes())->toBeTrue()
        ->and(V::make([], ['items.*.qty' => 'required'])->passes())->toBeTrue();
});

it('wildcard de mensagens e atributos: "items.*.qty"', function (): void {
    $v = V::make(
        ['items' => [['qty' => 'x']]],
        ['items.*.qty' => 'integer'],
        'pt',
        ['items.*.qty.integer' => 'A quantidade (:attribute) é inválida.'],
        ['items.*.qty' => 'quantidade'],
    );

    expect($v->errors()->first('items.0.qty'))->toBe('A quantidade (quantidade) é inválida.');
});

it('bail pára na primeira falha do campo', function (): void {
    $without = V::make(['f' => 'x'], ['f' => 'email|min:5'])->errors()->get('f');
    $with = V::make(['f' => 'x'], ['f' => 'bail|email|min:5'])->errors()->get('f');

    expect($without)->toHaveCount(2)->and($with)->toHaveCount(1);
});

it('stopOnFirstFailure pára no primeiro campo que falha', function (): void {
    $v = V::make(['a' => '', 'b' => ''], ['a' => 'required', 'b' => 'required'])->stopOnFirstFailure();

    expect($v->errors()->keys())->toBe(['a']);
});

it('sometimes só valida se o campo existir; nullable aceita null e ""', function (): void {
    expect(V::make([], ['f' => 'sometimes|required|email'])->passes())->toBeTrue()
        ->and(V::make(['f' => ''], ['f' => 'sometimes|required|email'])->passes())->toBeFalse()
        ->and(V::make(['f' => null], ['f' => 'nullable|email'])->passes())->toBeTrue()
        ->and(V::make(['f' => ''], ['f' => 'nullable|email'])->passes())->toBeTrue()
        ->and(V::make(['f' => 'x'], ['f' => 'nullable|email'])->passes())->toBeFalse();
});

it('sometimes(attr, rules, condição) acrescenta regras condicionalmente', function (): void {
    $make = fn (array $data): Validator => V::make($data, ['kind' => 'required'])
        ->sometimes('company', 'required|min:3', fn (array $data): bool => ($data['kind'] ?? null) === 'company');

    expect($make(['kind' => 'company'])->fails())->toBeTrue()
        ->and($make(['kind' => 'company', 'company' => 'ACME'])->passes())->toBeTrue()
        ->and($make(['kind' => 'person'])->passes())->toBeTrue();
});

it('after() corre depois das regras e pode acrescentar erros', function (): void {
    $v = V::make(['a' => 1, 'b' => 2], ['a' => 'required'])->after(function (Validator $validator): void {
        if ($validator->getData()['a'] !== 1) {
            $validator->errors()->add('a', 'nunca');
        }

        $validator->errors()->add('geral', 'Algo não bate certo.');
    });

    expect($v->fails())->toBeTrue()
        ->and($v->errors()->all())->toBe(['Algo não bate certo.']);
});

it('addFailure() regista uma falha de uma regra', function (): void {
    $v = V::make(['f' => 'x'], ['f' => 'required']);
    $v->after(fn (Validator $validator) => $validator->addFailure('f', 'max', ['3']));

    expect($v->errors()->first('f'))->toContain('não pode ter mais de 3')
        ->and($v->failed()['f'])->toHaveKey('Max');
});

it('validated() só devolve campos com regra; safe() tem only/except/all', function (): void {
    $v = V::make(['name' => 'Ana', 'email' => 'a@b.pt', 'extra' => 'x', 'nested' => ['a' => 1, 'b' => 2]], [
        'name' => 'required', 'email' => 'required|email', 'nested.a' => 'integer', 'missing' => 'nullable',
    ]);

    expect($v->validated())->toBe(['name' => 'Ana', 'email' => 'a@b.pt', 'nested' => ['a' => 1]])
        ->and($v->safe()->all())->toBe(['name' => 'Ana', 'email' => 'a@b.pt', 'nested' => ['a' => 1]])
        ->and($v->safe()->only('name'))->toBe(['name' => 'Ana'])
        ->and($v->safe()->except(['name', 'nested']))->toBe(['email' => 'a@b.pt'])
        ->and($v->safe()->name)->toBe('Ana')
        ->and($v->safe()->has('email'))->toBeTrue()
        ->and($v->safe()['email'])->toBe('a@b.pt')
        ->and($v->safe()->merge(['x' => 1])->all())->toHaveKey('x');
});

it('validated() com wildcards devolve a estrutura aninhada', function (): void {
    $v = V::make(['items' => [['qty' => 1, 'x' => 'a'], ['qty' => 2, 'x' => 'b']]], ['items.*.qty' => 'integer']);

    expect($v->validated())->toBe(['items' => [['qty' => 1], ['qty' => 2]]]);
});

it('validate() lança ValidationException com os erros; validated() também', function (): void {
    $v = V::make(['email' => 'x'], ['email' => 'email']);

    try {
        $v->validate();
        $this->fail('devia lançar');
    } catch (ValidationException $e) {
        expect($e->status)->toBe(422)
            ->and($e->errorBag)->toBe('default')
            ->and($e->redirectTo)->toBeNull()
            ->and($e->errors())->toBe(['email' => ['O campo e-mail tem de ser um endereço de e-mail válido.']])
            ->and($e->validator)->toBe($v)
            ->and($e->getMessage())->toContain('endereço de e-mail');
    }

    expect(fn () => V::make(['a' => ''], ['a' => 'required'])->validated())->toThrow(ValidationException::class);
});

it('ValidationException::withMessages e fluentes', function (): void {
    $e = ValidationException::withMessages(['email' => 'Já existe.', 'name' => ['a', 'b']])
        ->errorBag('login')->redirectTo('/entrar')->status(400);

    expect($e->errors())->toBe(['email' => ['Já existe.'], 'name' => ['a', 'b']])
        ->and($e->errorBag)->toBe('login')
        ->and($e->redirectTo)->toBe('/entrar')
        ->and($e->status)->toBe(400)
        ->and($e->getMessage())->toBe('Já existe. (and 2 more errors)');
});

it('exclude* removem o campo de validated() e não o validam', function (): void {
    $data = ['type' => 'person', 'company' => 'ACME', 'vat' => '123', 'nick' => 'x', 'note' => 'n'];

    $v = V::make($data, [
        'type' => 'required',
        'company' => 'exclude_if:type,person|required|min:10',
        'vat' => 'exclude_unless:type,company|min:10',
        'nick' => 'exclude_with:note|min:10',
        'note' => 'exclude_without:missing_field',
        'other' => 'exclude|required',
    ]);

    expect($v->passes())->toBeTrue()
        ->and($v->validated())->toBe(['type' => 'person']);

    $v = V::make(['type' => 'company', 'company' => 'ACME'], ['type' => 'required', 'company' => 'exclude_if:type,person|min:10']);
    expect($v->fails())->toBeTrue();
});

it('mensagens personalizadas: campo.regra > regra, e nomes de atributos', function (): void {
    $v = V::make(
        ['email' => '', 'name' => ''],
        ['email' => 'required', 'name' => 'required'],
        'pt',
        ['email.required' => 'Diz-nos o :attribute!', 'required' => 'Falta :attribute.'],
        ['email' => 'e-mail de contacto'],
    );

    expect($v->errors()->first('email'))->toBe('Diz-nos o e-mail de contacto!')
        ->and($v->errors()->first('name'))->toBe('Falta nome.');

    $v = V::make(['f' => ''], ['f' => 'required'], 'pt')->setCustomMessages(['f.required' => 'X'])->setAttributeNames(['f' => 'Fx']);
    expect($v->errors()->first('f'))->toBe('X');
});

it('mensagem personalizada por tipo (size)', function (): void {
    $v = V::make(['a' => 'x', 'b' => 1], ['a' => 'min:3', 'b' => 'numeric|min:3'], 'pt', [
        'min' => ['string' => 'Texto curto: :attribute', 'numeric' => 'Número baixo: :attribute'],
    ]);

    expect($v->errors()->first('a'))->toBe('Texto curto: a')
        ->and($v->errors()->first('b'))->toBe('Número baixo: b');
});

it('validation.custom e validation.attributes do projecto sobrepõem os da framework', function (): void {
    $dir = sys_get_temp_dir().'/slendie-vlang-'.bin2hex(random_bytes(4));
    mkdir($dir.'/pt', 0777, true);
    file_put_contents($dir.'/pt/validation.php', "<?php return ['custom' => ['email' => ['required' => 'O seu e-mail faz falta.']], 'attributes' => ['phone' => 'telemóvel'], 'required' => 'Preencha :attribute.'];");

    $factory = new Slendie\Validation\Factory(V::translator('pt', $dir));
    $v = $factory->make(['email' => '', 'phone' => ''], ['email' => 'required', 'phone' => 'required', 'age' => 'required']);

    expect($v->errors()->first('email'))->toBe('O seu e-mail faz falta.')
        ->and($v->errors()->first('phone'))->toBe('Preencha telemóvel.')
        ->and($v->errors()->first('age'))->toBe('Preencha age.')
        // o que o projecto não sobrepõe continua a vir da framework
        ->and($factory->make(['x' => 'a'], ['x' => 'email'])->errors()->first('x'))->toContain('endereço de e-mail');

    unlink($dir.'/pt/validation.php');
});

it('values: o valor aparece legível em :value', function (): void {
    $dir = sys_get_temp_dir().'/slendie-vlang-'.bin2hex(random_bytes(4));
    mkdir($dir.'/pt', 0777, true);
    file_put_contents($dir.'/pt/validation.php', "<?php return ['values' => ['type' => ['company' => 'empresa']]];");

    $v = (new Slendie\Validation\Factory(V::translator('pt', $dir)))
        ->make(['type' => 'company', 'name' => ''], ['name' => 'required_if:type,company']);

    expect($v->errors()->first('name'))->toBe('O campo nome é obrigatório quando type é empresa.');

    unlink($dir.'/pt/validation.php');
});

it('Factory::extend() regista regras personalizadas com mensagem', function (): void {
    $factory = V::factory();
    $factory->extend('even', fn (string $attribute, mixed $value): bool => is_int($value) && $value % 2 === 0, 'O :attribute tem de ser par.');

    $v = $factory->make(['n' => 3, 'm' => 4], ['n' => 'even', 'm' => 'even']);

    expect($v->errors()->keys())->toBe(['n'])
        ->and($v->errors()->first('n'))->toBe('O n tem de ser par.');
});

it('Rule::in / notIn aceitam arrays, variádicos, enums e valores com vírgulas', function (): void {
    expect(V::make(['f' => 'a'], ['f' => [Rule::in(['a', 'b'])]])->passes())->toBeTrue()
        ->and(V::make(['f' => 'c'], ['f' => [Rule::in('a', 'b')]])->passes())->toBeFalse()
        ->and(V::make(['f' => 'a,b'], ['f' => [Rule::in(['a,b', 'c'])]])->passes())->toBeTrue()
        ->and(V::make(['f' => 'a'], ['f' => [Rule::notIn(['a'])]])->passes())->toBeFalse()
        ->and(V::make(['f' => 'new'], ['f' => [Rule::in(Status::cases())]])->passes())->toBe(in_array('new', array_map(fn ($c) => (string) $c->value, Status::cases()), true))
        ->and(V::make(['f' => 'say "hi"'], ['f' => [Rule::in(['say "hi"'])]])->passes())->toBeTrue();
});

it('Rule::enum e a regra enum:Classe', function (): void {
    $valid = (string) Status::cases()[0]->value;

    expect(V::make(['f' => $valid], ['f' => [Rule::enum(Status::class)]])->passes())->toBeTrue()
        ->and(V::make(['f' => 'nope'], ['f' => [Rule::enum(Status::class)]])->passes())->toBeFalse()
        ->and(V::make(['f' => $valid], ['f' => ['enum:'.Status::class]])->passes())->toBeTrue()
        ->and(V::make(['f' => 'nope'], ['f' => ['enum:'.Status::class]])->passes())->toBeFalse()
        ->and(V::make(['f' => Status::cases()[0]], ['f' => [Rule::enum(Status::class)]])->passes())->toBeTrue()
        ->and(V::make(['f' => $valid], ['f' => [Rule::enum(Status::class)->except([Status::cases()[0]])]])->passes())->toBeFalse()
        ->and(V::make(['f' => $valid], ['f' => [Rule::enum(Status::class)->only([Status::cases()[0]])]])->passes())->toBeTrue()
        ->and(V::make(['f' => 'nope'], ['f' => [Rule::enum(Status::class)]], 'pt')->errors()->first('f'))->toBe('O valor escolhido para f é inválido.');
});

it('Rule::requiredIf / prohibitedIf com bool e closure', function (): void {
    expect(V::make(['f' => ''], ['f' => [Rule::requiredIf(true)]])->passes())->toBeFalse()
        ->and(V::make(['f' => ''], ['f' => [Rule::requiredIf(false)]])->passes())->toBeTrue()
        ->and(V::make([], ['f' => [Rule::requiredIf(fn () => true)]])->passes())->toBeFalse()
        ->and(V::make(['f' => 'x'], ['f' => [Rule::prohibitedIf(fn () => true)]])->passes())->toBeFalse()
        ->and(V::make(['f' => 'x'], ['f' => [Rule::prohibitedIf(false)]])->passes())->toBeTrue();
});

it('Rule::when escolhe as regras com base nos dados', function (): void {
    $rules = ['f' => [Rule::when(fn (array $data): bool => ($data['strict'] ?? false) === true, 'required|min:5', ['nullable'])]];

    expect(V::make(['strict' => true, 'f' => 'abc'], $rules)->passes())->toBeFalse()
        ->and(V::make(['strict' => true, 'f' => 'abcde'], $rules)->passes())->toBeTrue()
        ->and(V::make(['strict' => false, 'f' => null], $rules)->passes())->toBeTrue()
        ->and(V::make(['f' => ''], ['f' => [Rule::when(true, ['required'])]])->passes())->toBeFalse();
});

it('Password: min, letters, mixedCase, numbers, symbols, max', function (): void {
    $rule = fn () => ['p' => ['required', Password::min(8)->letters()->mixedCase()->numbers()->symbols()]];

    expect(V::make(['p' => 'Abcdef1!'], $rule())->passes())->toBeTrue()
        ->and(V::make(['p' => 'abcdef1!'], $rule())->errors()->first('p'))->toBe('O campo p tem de conter pelo menos uma letra maiúscula e uma minúscula.')
        ->and(V::make(['p' => 'Abcdefg!'], $rule())->errors()->first('p'))->toBe('O campo p tem de conter pelo menos um número.')
        ->and(V::make(['p' => 'Abcdefg1'], $rule())->errors()->first('p'))->toBe('O campo p tem de conter pelo menos um símbolo.')
        ->and(V::make(['p' => '1234567!'], $rule())->errors()->first('p'))->toBe('O campo p tem de conter pelo menos uma letra.')
        ->and(V::make(['p' => 'Ab1!'], $rule())->errors()->first('p'))->toBe('O campo p tem de ter pelo menos 8 caracteres.')
        ->and(V::make(['p' => 'Ab1!'], $rule(), 'en')->errors()->first('p'))->toBe('The p field must be at least 8 characters.')
        ->and(V::make(['p' => 'abcdefghij'], ['p' => Password::min(4)->max(6)])->errors()->first('p'))->toBe('O campo p não pode ter mais de 6 caracteres.')
        ->and(V::make(['p' => 'abcd'], ['p' => Rule::password(4)])->passes())->toBeTrue()
        ->and(V::make(['p' => 'abc'], ['p' => Password::min(4)])->errors()->count())->toBe(1);

    Password::defaults(fn () => Password::min(3)->numbers());
    expect(V::make(['p' => 'abc'], ['p' => Password::default()])->passes())->toBeFalse();
    Password::defaults(null);
    expect(V::make(['p' => 'abc'], ['p' => Password::default()])->passes())->toBeFalse();
});

it('size é contextual: string, numeric, array e ficheiro', function (): void {
    $file = V::upload('doc.txt', str_repeat('a', 2048));

    expect(V::make(['f' => 'abcd'], ['f' => 'max:3'])->passes())->toBeFalse()
        ->and(V::make(['f' => '10'], ['f' => 'max:3'])->passes())->toBeTrue()          // sem numeric: 2 caracteres
        ->and(V::make(['f' => '10'], ['f' => 'numeric|max:3'])->passes())->toBeFalse()  // com numeric: valor 10
        ->and(V::make(['f' => [1, 2, 3, 4]], ['f' => 'array|max:3'])->passes())->toBeFalse()
        ->and(V::make(['f' => $file], ['f' => 'file|max:2'])->passes())->toBeTrue()      // 2 KB
        ->and(V::make(['f' => $file], ['f' => 'file|max:1'])->passes())->toBeFalse()
        ->and(V::make(['f' => $file], ['f' => 'file|size:2'])->passes())->toBeTrue()
        ->and(V::make(['f' => $file], ['f' => 'file|min:3'])->errors()->first('f'))->toBe('O campo f tem de ter pelo menos 3 kilobytes.')
        ->and(V::make(['f' => $file], ['f' => 'file|between:1,3'])->passes())->toBeTrue();
});

it('file, image, mimes, mimetypes e extensions com UploadedFile', function (): void {
    $png = V::upload('foto.png', V::png());
    $fake = V::upload('foto.png', 'isto não é uma imagem');
    $csv = V::upload('dados.csv', "a,b\n1,2\n");
    $upload = V::upload('x.png', 'x');
    $failed = new Slendie\Http\UploadedFile($upload->path(), 'x.png', null, 1, UPLOAD_ERR_PARTIAL, true);

    expect(V::make(['f' => $png], ['f' => 'file|image'])->passes())->toBeTrue()
        ->and(V::make(['f' => $fake], ['f' => 'image'])->passes())->toBeFalse()
        ->and(V::make(['f' => $png], ['f' => 'mimes:jpg,png'])->passes())->toBeTrue()
        ->and(V::make(['f' => $png], ['f' => 'mimes:pdf'])->passes())->toBeFalse()
        ->and(V::make(['f' => $fake], ['f' => 'mimes:png'])->passes())->toBeFalse()
        ->and(V::make(['f' => $csv], ['f' => 'mimes:csv'])->passes())->toBeTrue()
        ->and(V::make(['f' => $csv], ['f' => 'mimes:png'])->passes())->toBeFalse()
        ->and(V::make(['f' => $png], ['f' => 'mimetypes:image/png'])->passes())->toBeTrue()
        ->and(V::make(['f' => $png], ['f' => 'mimetypes:image/*'])->passes())->toBeTrue()
        ->and(V::make(['f' => $png], ['f' => 'mimetypes:video/mp4'])->passes())->toBeFalse()
        ->and(V::make(['f' => $png], ['f' => 'extensions:png,jpg'])->passes())->toBeTrue()
        ->and(V::make(['f' => $png], ['f' => 'extensions:gif'])->passes())->toBeFalse()
        ->and(V::make(['f' => 'texto'], ['f' => 'file'])->passes())->toBeFalse()
        ->and(V::make(['f' => $failed], ['f' => 'file'])->passes())->toBeFalse()
        ->and(V::make(['f' => $failed], ['f' => 'max:100'])->passes())->toBeFalse();
});

it('Rule::file() combina tipos e tamanho', function (): void {
    $png = V::upload('foto.png', V::png());
    $big = V::upload('foto.png', V::png().str_repeat('0', 3000));

    $rule = fn () => ['f' => [Rule::file()->types(['png', 'jpg'])->max(2)]];

    expect(V::make(['f' => $png], $rule())->passes())->toBeTrue()
        ->and(V::make(['f' => $big], $rule())->errors()->first('f'))->toBe('O campo f não pode ter mais de 2 kilobytes.')
        ->and(V::make(['f' => 'x'], ['f' => [Rule::file()]])->errors()->first('f'))->toBe('O campo f tem de ser um ficheiro.')
        ->and(V::make(['f' => $png], ['f' => [Rule::file()->image()->between(0, 5)->min(0)->size(0)]])->fails())->toBeTrue();
});

it('data: setData / setRules reiniciam a validação', function (): void {
    $v = V::make(['a' => ''], ['a' => 'required']);
    expect($v->fails())->toBeTrue();

    $v->setData(['a' => 'x']);
    expect($v->passes())->toBeTrue();

    $v->setRules(['a' => 'min:3']);
    expect($v->fails())->toBeTrue()->and($v->getRules())->toHaveKey('a');
});

it('Validator::make() e o helper validator() usam a Factory', function (): void {
    expect(Validator::make(['a' => ''], ['a' => 'required'])->fails())->toBeTrue()
        ->and(validator(['a' => 'x'], ['a' => 'required'])->passes())->toBeTrue()
        ->and(validator())->toBeInstanceOf(Slendie\Validation\Factory::class)
        ->and(validator()->validate(['a' => 'x'], ['a' => 'required']))->toBe(['a' => 'x']);
});
