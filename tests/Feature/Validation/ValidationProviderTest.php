<?php

declare(strict_types=1);

use Slendie\Support\Facades\Validator as ValidatorFacade;
use Slendie\Tests\Fixtures\Console\ConsoleProject;
use Slendie\Tests\Fixtures\Http\HttpHelpers as H;
use Slendie\Validation\Factory;
use Slendie\Validation\Translator;

afterEach(fn () => ConsoleProject::cleanup());

it('regista o Translator e o Factory como singletons, com aliases', function (): void {
    $app = H::app();

    expect($app->make('translator'))->toBe($app->make(Translator::class))
        ->and($app->make('validator'))->toBe($app->make(Factory::class))
        ->and($app->make('translator')->getLocale())->toBe('pt')
        ->and($app->make('translator')->getFallback())->toBe('en');
});

it('o locale vem de app.locale e o fallback de app.fallback_locale', function (): void {
    $app = H::app(['app.locale' => 'en', 'app.fallback_locale' => 'pt']);

    expect($app->make('translator')->getLocale())->toBe('en')
        ->and($app->make('translator')->getFallback())->toBe('pt')
        ->and(__('validation.required', ['attribute' => 'x']))->toBe('The x field is required.')
        ->and(validator(['x' => ''], ['x' => 'required'])->errors()->first('x'))->toBe('The x field is required.');
});

it('a facade Validator resolve o Factory', function (): void {
    H::app();

    expect(ValidatorFacade::make(['a' => ''], ['a' => 'required'])->fails())->toBeTrue()
        ->and(ValidatorFacade::validate(['a' => 'x'], ['a' => 'required']))->toBe(['a' => 'x'])
        ->and(ValidatorFacade::getTranslator())->toBeInstanceOf(Translator::class);

    ValidatorFacade::extend('always', fn () => true);
    expect(ValidatorFacade::make(['a' => 'x'], ['a' => 'always'])->passes())->toBeTrue();
});

it('o projecto sobrepõe os textos em lang/{locale}/validation.php', function (): void {
    $project = ConsoleProject::make(['lang/pt/validation.php' => "<?php return ['required' => 'Falta :attribute!'];"]);

    $project->app->bootstrap();

    expect($project->app->make('translator')->get('validation.required', ['attribute' => 'nome']))->toBe('Falta nome!')
        // o resto continua a vir da framework
        ->and($project->app->make('translator')->get('validation.email', ['attribute' => 'x']))->toContain('e-mail válido');
});

it('vendor:publish --tag=slendie-lang copia os textos para lang/', function (): void {
    $project = ConsoleProject::make();

    [$code] = $project->artisan('vendor:publish --tag=slendie-lang');

    expect($code)->toBe(0)
        ->and($project->read('lang/pt/validation.php'))->toContain("'required' => 'O campo :attribute é obrigatório.'")
        ->and($project->has('lang/en/validation.php'))->toBeTrue()
        ->and($project->has('lang/pt/pagination.php'))->toBeTrue();
});

it('make:request cria um FormRequest', function (): void {
    $project = ConsoleProject::make();

    [$code, $output] = $project->artisan('make:request StoreLeadRequest');

    expect($code)->toBe(0)
        ->and($output)->toContain('Request [app/Http/Requests/StoreLeadRequest.php] created successfully.');

    $source = $project->read('app/Http/Requests/StoreLeadRequest.php');

    expect($source)
        ->toContain('namespace App\\Http\\Requests;')
        ->toContain('use Slendie\\Http\\FormRequest;')
        ->toContain('final class StoreLeadRequest extends FormRequest')
        ->toContain('public function rules(): array')
        ->toContain('public function authorize(): bool')
        ->not->toContain('{{');

    exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($project->path('app/Http/Requests/StoreLeadRequest.php')).' 2>&1', $lint, $status);
    expect($status)->toBe(0, implode("\n", $lint));

    [$again, $message] = $project->artisan('make:request StoreLeadRequest');
    expect($again)->toBe(1)->and($message)->toContain('already exists');
});
