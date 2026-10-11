<?php

declare(strict_types=1);

namespace Slendie\View;

use Closure;
use InvalidArgumentException;
use LogicException;
use Slendie\Container\Container;
use Slendie\Contracts\ViewFactory;
use Slendie\Support\Str;

/**
 * O serviço `view`: cria views, partilha dados, regista composers e guarda o estado (secções, stacks, loops)
 * que o código compilado usa através de `$__env`.
 */
class Factory implements ViewFactory
{
    /** @var array<string, list<Closure|string>> padrão => composers */
    protected array $composers = [];

    /** @var array<string, string> */
    protected array $sections = [];

    /** @var list<string> */
    protected array $sectionStack = [];

    /** @var array<string, list<string>> */
    protected array $pushes = [];

    /** @var array<string, list<string>> */
    protected array $prepends = [];

    /** @var list<array{0: 'push'|'prepend', 1: string}> */
    protected array $pushStack = [];

    /** @var array<string, true> */
    protected array $renderedOnce = [];

    /** @var list<Loop> */
    protected array $loops = [];

    protected int $renderCount = 0;

    /**
     * @param  array<string, mixed>  $shared
     */
    public function __construct(
        protected Finder $finder,
        protected Engine $engine,
        protected array $shared = [],
        protected ?Container $container = null,
    ) {
    }

    // -------------------------------------------------------------------------
    // Criar e renderizar
    // -------------------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $data
     */
    public function make(string $view, array $data = []): View
    {
        return new View($this, $this->engine, $view, $this->finder->find($view), $data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function render(string $view, array $data = []): string
    {
        return $this->make($view, $data)->render();
    }

    public function exists(string $view): bool
    {
        return $this->finder->exists($view);
    }

    public function getFinder(): Finder
    {
        return $this->finder;
    }

    public function getEngine(): Engine
    {
        return $this->engine;
    }

    // -------------------------------------------------------------------------
    // Dados partilhados, composers, namespaces
    // -------------------------------------------------------------------------

    /**
     * @param  string|array<string, mixed>  $key
     */
    public function share(string|array $key, mixed $value = null): mixed
    {
        if (is_array($key)) {
            $this->shared = [...$this->shared, ...$key];

            return null;
        }

        return $this->shared[$key] = $value;
    }

    /** @return array<string, mixed> */
    public function getShared(): array
    {
        return $this->shared;
    }

    /**
     * Regista um callback chamado com a `View` antes de ela ser renderizada. Aceita `*` como wildcard.
     *
     * @param  string|list<string>  $views
     * @param  Closure|string  $callback  closure, `Classe` (método compose) ou `Classe@metodo`
     */
    public function composer(string|array $views, Closure|string $callback): void
    {
        foreach ((array) $views as $pattern) {
            $this->composers[$pattern][] = $callback;
        }
    }

    public function callComposers(View $view): void
    {
        foreach ($this->composers as $pattern => $callbacks) {
            if (! Str::is($pattern, $view->name())) {
                continue;
            }

            foreach ($callbacks as $callback) {
                $this->callComposer($callback, $view);
            }
        }
    }

    protected function callComposer(Closure|string $callback, View $view): void
    {
        if ($callback instanceof Closure) {
            $callback($view);

            return;
        }

        if ($this->container === null) {
            throw new LogicException('Composers em classe precisam de um container.');
        }

        [$class, $method] = str_contains($callback, '@') ? explode('@', $callback, 2) : [$callback, 'compose'];

        $instance = $this->container->make($class);

        if (! is_object($instance) || ! method_exists($instance, $method)) {
            throw new LogicException("O composer [{$callback}] não tem o método [{$method}].");
        }

        $instance->{$method}($view);
    }

    /**
     * @param  string|list<string>  $paths
     */
    public function addNamespace(string $namespace, string|array $paths): static
    {
        $this->finder->addNamespace($namespace, $paths);

        return $this;
    }

    /**
     * @param  string|list<string>  $paths
     */
    public function prependNamespace(string $namespace, string|array $paths): static
    {
        $this->finder->prependNamespace($namespace, $paths);

        return $this;
    }

    public function addLocation(string $path): static
    {
        $this->finder->addLocation($path);

        return $this;
    }

    // -------------------------------------------------------------------------
    // Includes (usados pelo código compilado; $scope = get_defined_vars() da view pai)
    // -------------------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $scope
     * @param  array<string, mixed>  $data
     */
    public function renderInclude(array $scope, string $view, array $data = []): string
    {
        return $this->make($view, [...$this->stripScope($scope), ...$data])->render();
    }

    /**
     * @param  array<string, mixed>  $scope
     * @param  array<string, mixed>  $data
     */
    public function renderIncludeIf(array $scope, string $view, array $data = []): string
    {
        return $this->exists($view) ? $this->renderInclude($scope, $view, $data) : '';
    }

    /**
     * @param  array<string, mixed>  $scope
     * @param  array<string, mixed>  $data
     */
    public function renderIncludeWhen(array $scope, mixed $condition, string $view, array $data = []): string
    {
        return $condition ? $this->renderInclude($scope, $view, $data) : '';
    }

    /**
     * @param  array<string, mixed>  $scope
     * @param  array<string, mixed>  $data
     */
    public function renderIncludeUnless(array $scope, mixed $condition, string $view, array $data = []): string
    {
        return $condition ? '' : $this->renderInclude($scope, $view, $data);
    }

    /**
     * @param  array<string, mixed>  $scope
     * @param  list<string>  $views
     * @param  array<string, mixed>  $data
     */
    public function renderIncludeFirst(array $scope, array $views, array $data = []): string
    {
        foreach ($views as $view) {
            if ($this->exists($view)) {
                return $this->renderInclude($scope, $view, $data);
            }
        }

        throw new ViewNotFoundException('Nenhuma das views existe: ['.implode(', ', $views).'].');
    }

    /**
     * @param  array<string, mixed>  $scope
     * @return array<string, mixed>
     */
    protected function stripScope(array $scope): array
    {
        unset($scope['__env'], $scope['__data'], $scope['__path'], $scope['this']);

        return $scope;
    }

    /**
     * Para @once: true só na primeira vez que o bloco é pedido (por ciclo de render).
     */
    public function renderOnce(string $id): bool
    {
        if (isset($this->renderedOnce[$id])) {
            return false;
        }

        return $this->renderedOnce[$id] = true;
    }

    // -------------------------------------------------------------------------
    // Secções
    // -------------------------------------------------------------------------

    /**
     * Com `$content` define a secção de uma vez (escapada); sem ele abre um buffer até ao @endsection.
     */
    public function startSection(string $section, mixed $content = null): void
    {
        if ($content === null) {
            ob_start();
            $this->sectionStack[] = $section;

            return;
        }

        $this->extendSection($section, e($content));
    }

    public function stopSection(bool $overwrite = false): string
    {
        $last = array_pop($this->sectionStack);

        if ($last === null) {
            throw new InvalidArgumentException('Não se pode terminar uma secção que não foi iniciada.');
        }

        $content = (string) ob_get_clean();

        if ($overwrite) {
            $this->sections[$last] = $content;
        } else {
            $this->extendSection($last, $content);
        }

        return $last;
    }

    public function appendSection(): string
    {
        $last = array_pop($this->sectionStack);

        if ($last === null) {
            throw new InvalidArgumentException('Não se pode terminar uma secção que não foi iniciada.');
        }

        $this->sections[$last] = ($this->sections[$last] ?? '').(string) ob_get_clean();

        return $last;
    }

    /**
     * Uma secção já definida por uma view filha mantém-se; o `@parent` dela recebe o conteúdo novo.
     */
    protected function extendSection(string $section, string $content): void
    {
        if (isset($this->sections[$section])) {
            $content = str_replace(static::parentPlaceholder($section), $content, $this->sections[$section]);
        }

        $this->sections[$section] = $content;
    }

    public function yieldSection(): string
    {
        return $this->yieldContent($this->stopSection());
    }

    public function yieldContent(string $section, mixed $default = ''): string
    {
        $content = $this->sections[$section] ?? e($default);

        return str_replace(static::parentPlaceholder($section), '', $content);
    }

    /** Marcador que o `@parent` deixa na secção da filha até a secção do pai ser conhecida. */
    public function yieldParent(): string
    {
        $current = end($this->sectionStack);

        return static::parentPlaceholder($current === false ? '' : $current);
    }

    public static function parentPlaceholder(string $section): string
    {
        return '##parent-placeholder-'.sha1($section).'##';
    }

    public function hasSection(string $name): bool
    {
        return isset($this->sections[$name]);
    }

    public function sectionMissing(string $name): bool
    {
        return ! $this->hasSection($name);
    }

    public function getSection(string $name, ?string $default = null): ?string
    {
        return $this->sections[$name] ?? $default;
    }

    /** @return array<string, string> */
    public function getSections(): array
    {
        return $this->sections;
    }

    // -------------------------------------------------------------------------
    // Stacks (@push / @prepend / @stack)
    // -------------------------------------------------------------------------

    public function startPush(string $section, ?string $content = null): void
    {
        if ($content !== null) {
            $this->pushes[$section][] = $content;

            return;
        }

        ob_start();
        $this->pushStack[] = ['push', $section];
    }

    public function stopPush(): string
    {
        return $this->closeStack('push');
    }

    public function startPrepend(string $section, ?string $content = null): void
    {
        if ($content !== null) {
            $this->prepends[$section][] = $content;

            return;
        }

        ob_start();
        $this->pushStack[] = ['prepend', $section];
    }

    public function stopPrepend(): string
    {
        return $this->closeStack('prepend');
    }

    /**
     * @param  'push'|'prepend'  $expected
     */
    protected function closeStack(string $expected): string
    {
        $last = array_pop($this->pushStack);

        if ($last === null || $last[0] !== $expected) {
            throw new InvalidArgumentException("@end{$expected} sem o respectivo @{$expected}.");
        }

        $content = (string) ob_get_clean();

        if ($expected === 'push') {
            $this->pushes[$last[1]][] = $content;
        } else {
            $this->prepends[$last[1]][] = $content;
        }

        return $last[1];
    }

    public function yieldPushContent(string $section, string $default = ''): string
    {
        if (! isset($this->pushes[$section]) && ! isset($this->prepends[$section])) {
            return $default;
        }

        return implode('', array_reverse($this->prepends[$section] ?? [])).implode('', $this->pushes[$section] ?? []);
    }

    // -------------------------------------------------------------------------
    // $loop
    // -------------------------------------------------------------------------

    public function addLoop(mixed $data): void
    {
        $this->loops[] = new Loop(
            is_countable($data) ? count($data) : null,
            count($this->loops) + 1,
            $this->loops === [] ? null : $this->loops[array_key_last($this->loops)],
        );
    }

    public function incrementLoopIndices(): void
    {
        if ($this->loops !== []) {
            $this->loops[array_key_last($this->loops)]->advance();
        }
    }

    public function popLoop(): void
    {
        array_pop($this->loops);
    }

    public function getLastLoop(): ?Loop
    {
        return $this->loops === [] ? null : $this->loops[array_key_last($this->loops)];
    }

    // -------------------------------------------------------------------------
    // Estado de renderização
    // -------------------------------------------------------------------------

    public function incrementRender(): void
    {
        $this->renderCount++;
    }

    public function decrementRender(): void
    {
        $this->renderCount--;
    }

    public function doneRendering(): bool
    {
        return $this->renderCount === 0;
    }

    /** Limpa secções, stacks e loops quando termina a view de topo. */
    public function flushStateIfDoneRendering(): void
    {
        if ($this->doneRendering()) {
            $this->flushState();
        }
    }

    public function flushState(): void
    {
        $this->renderCount = 0;
        $this->sections = [];
        $this->sectionStack = [];
        $this->pushes = [];
        $this->prepends = [];
        $this->pushStack = [];
        $this->renderedOnce = [];
        $this->loops = [];
    }
}
