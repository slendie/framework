<?php

declare(strict_types=1);

namespace Slendie\View\Compiler\Directives;

/**
 * Directivas condicionais: if, unless, isset, switch, env e afins.
 */
trait Conditionals
{
    protected function compileIf(string $expression): string
    {
        return "<?php if({$expression}): ?>";
    }

    protected function compileElseif(string $expression): string
    {
        return "<?php elseif({$expression}): ?>";
    }

    protected function compileElse(string $expression): string
    {
        return '<?php else: ?>';
    }

    protected function compileEndif(string $expression): string
    {
        return '<?php endif; ?>';
    }

    protected function compileUnless(string $expression): string
    {
        return "<?php if (! ({$expression})): ?>";
    }

    protected function compileEndunless(string $expression): string
    {
        return '<?php endif; ?>';
    }

    protected function compileIsset(string $expression): string
    {
        return "<?php if(isset({$expression})): ?>";
    }

    protected function compileEndisset(string $expression): string
    {
        return '<?php endif; ?>';
    }

    protected function compileEndempty(string $expression): string
    {
        return '<?php endif; ?>';
    }

    protected function compileSwitch(string $expression): string
    {
        $this->switchStack[] = true;

        // Fica aberto: o primeiro @case continua o mesmo bloco PHP (entre os dois só pode haver espaço em branco).
        return "<?php switch({$expression}):";
    }

    protected function compileCase(string $expression): string
    {
        if ($this->switchStack !== [] && $this->switchStack[array_key_last($this->switchStack)]) {
            $this->switchStack[array_key_last($this->switchStack)] = false;

            return "case {$expression}: ?>";
        }

        return "<?php case {$expression}: ?>";
    }

    protected function compileDefault(string $expression): string
    {
        if ($this->switchStack !== [] && $this->switchStack[array_key_last($this->switchStack)]) {
            $this->switchStack[array_key_last($this->switchStack)] = false;

            return 'default: ?>';
        }

        return '<?php default: ?>';
    }

    protected function compileEndswitch(string $expression): string
    {
        array_pop($this->switchStack);

        return '<?php endswitch; ?>';
    }

    protected function compileEnv(string $expression): string
    {
        $arguments = str_starts_with(trim($expression), '[') ? '...'.$expression : $expression;

        return "<?php if(app()->environment({$arguments})): ?>";
    }

    protected function compileEndenv(string $expression): string
    {
        return '<?php endif; ?>';
    }

    protected function compileProduction(string $expression): string
    {
        return "<?php if(app()->environment('production')): ?>";
    }

    protected function compileEndproduction(string $expression): string
    {
        return '<?php endif; ?>';
    }
}
