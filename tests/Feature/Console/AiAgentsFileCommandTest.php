<?php

declare(strict_types=1);

use Slendie\Foundation\ServiceProvider;
use Slendie\Tests\Fixtures\Console\ConsoleProject;

afterEach(function (): void {
    ConsoleProject::cleanup();
    ServiceProvider::flushPublishes();
});

function agentsProject(array $files = []): ConsoleProject
{
    return ConsoleProject::make([
        'config/app.php' => "<?php\nreturn ['name' => 'Minha Loja', 'env' => 'local', 'debug' => false, 'url' => 'http://localhost', 'timezone' => 'UTC', 'locale' => 'pt', 'providers' => []];\n",
        ...$files,
    ]);
}

it('ai:agents-file escreve o AGENTS.md com o nome da aplicação', function (): void {
    $project = agentsProject();

    [$code, $output] = $project->artisan('ai:agents-file');

    $contents = (string) file_get_contents($project->path('AGENTS.md'));

    expect($code)->toBe(0)
        ->and($output)->toContain('AGENTS.md created')
        ->and($contents)->toContain('# AGENTS.md — Minha Loja')
        ->and($contents)->toContain('O que não existe')
        ->and($contents)->not->toContain('{{ name }}');
});

it('ai:agents-file não substitui um AGENTS.md existente sem --force', function (): void {
    $project = agentsProject(['AGENTS.md' => 'meu']);

    [$code, $output] = $project->artisan('ai:agents-file');

    expect($code)->toBe(1)
        ->and($output)->toContain('--force')
        ->and(file_get_contents($project->path('AGENTS.md')))->toBe('meu');

    [$code] = $project->artisan('ai:agents-file --force');

    expect($code)->toBe(0)
        ->and(file_get_contents($project->path('AGENTS.md')))->toContain('# AGENTS.md');
});

it('ai:agents-file usa o stub do projecto quando existe', function (): void {
    $project = agentsProject(['stubs/AGENTS.md.stub' => "Regras de {{ name }}\n"]);

    $project->artisan('ai:agents-file');

    expect(file_get_contents($project->path('AGENTS.md')))->toBe("Regras de Minha Loja\n");
});
