<?php

declare(strict_types=1);

namespace Slendie\View\Compiler\Directives;

/**
 * Directivas de formulários: csrf, method e error.
 */
trait Forms
{
    protected function compileCsrf(string $expression): string
    {
        return '<?php echo csrf_field(); ?>';
    }

    protected function compileMethod(string $expression): string
    {
        return "<?php echo method_field({$expression}); ?>";
    }

    /**
     * `@error('campo') ... {{ $message }} ... @enderror` — usa `$errors` (ViewErrorBag, partilhado pelo ShareErrorsFromSession).
     */
    protected function compileError(string $expression): string
    {
        $field = $this->splitArguments($expression)[0];
        $n = ++$this->errorCounter;

        return "<?php \$__msg{$n} = \$message ?? null; \$__bag{$n} = \$errors ?? new \\Slendie\\View\\ViewErrorBag(); "
            ."if (\$__bag{$n}->has({$field})): \$message = \$__bag{$n}->first({$field}); ?>";
    }

    protected function compileEnderror(string $expression): string
    {
        if ($this->errorCounter <= 0) {
            $this->fail('@enderror sem @error');
        }

        $n = $this->errorCounter--;

        return "<?php \$message = \$__msg{$n}; endif; ?>";
    }
}
