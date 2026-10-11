<?php

declare(strict_types=1);

namespace Slendie\View\Compiler\Directives;

/**
 * php inline, dd/dump, inject, class e os atributos booleanos (checked, selected, ...).
 * O bloco `@php ... @endphp` e o `@verbatim` são extraídos antes das directivas, no pipeline.
 */
trait Misc
{
    protected function compilePhp(string $expression): string
    {
        if (trim($expression) === '') {
            $this->fail('@php sem @endphp');
        }

        return "<?php ({$expression}); ?>";
    }

    protected function compileDd(string $expression): string
    {
        return "<?php dd({$expression}); ?>";
    }

    protected function compileDump(string $expression): string
    {
        return "<?php dump({$expression}); ?>";
    }

    /**
     * `@inject('metrics', 'App\Services\Metrics')` → `$metrics = app('App\Services\Metrics')`.
     */
    protected function compileInject(string $expression): string
    {
        $arguments = $this->splitArguments($expression);

        if (count($arguments) !== 2 || preg_match('/^([\'"])([A-Za-z_]\w*)\1$/', $arguments[0], $m) !== 1) {
            $this->fail("A directiva @inject espera ('variavel', 'Classe')");
        }

        return "<?php \${$m[2]} = app({$arguments[1]}); ?>";
    }

    /**
     * `@class(['btn', 'btn-primary' => $primary])` → `class="btn btn-primary"`.
     */
    protected function compileClass(string $expression): string
    {
        return "<?php echo 'class=\"'.e(\\Slendie\\Support\\Arr::toCssClasses({$expression})).'\"'; ?>";
    }

    protected function compileChecked(string $expression): string
    {
        return "<?php if({$expression}) { echo 'checked'; } ?>";
    }

    protected function compileSelected(string $expression): string
    {
        return "<?php if({$expression}) { echo 'selected'; } ?>";
    }

    protected function compileDisabled(string $expression): string
    {
        return "<?php if({$expression}) { echo 'disabled'; } ?>";
    }

    protected function compileReadonly(string $expression): string
    {
        return "<?php if({$expression}) { echo 'readonly'; } ?>";
    }

    protected function compileRequired(string $expression): string
    {
        return "<?php if({$expression}) { echo 'required'; } ?>";
    }
}
