<?php

declare(strict_types=1);

namespace Slendie\View\Compiler\Directives;

/**
 * Directivas de ciclos: foreach/forelse (com `$loop`), for, while, break e continue.
 */
trait Loops
{
    protected function compileForeach(string $expression): string
    {
        [$items, $variables] = $this->parseLoop($expression, 'foreach');

        return "<?php \$__currentLoopData = {$items}; \$__env->addLoop(\$__currentLoopData); foreach(\$__currentLoopData as {$variables}): "
            .'$__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>';
    }

    protected function compileEndforeach(string $expression): string
    {
        return '<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>';
    }

    protected function compileForelse(string $expression): string
    {
        [$items, $variables] = $this->parseLoop($expression, 'forelse');

        $empty = '$__empty_'.++$this->forElseCounter;

        return "<?php {$empty} = true; \$__currentLoopData = {$items}; \$__env->addLoop(\$__currentLoopData); foreach(\$__currentLoopData as {$variables}): "
            ."{$empty} = false; \$__env->incrementLoopIndices(); \$loop = \$__env->getLastLoop(); ?>";
    }

    /**
     * `@empty` sem parênteses fecha um @forelse; `@empty($x)` é a condição `empty()`.
     */
    protected function compileEmpty(string $expression): string
    {
        if (trim($expression) !== '') {
            return "<?php if(empty({$expression})): ?>";
        }

        if ($this->forElseCounter <= 0) {
            $this->fail('@empty sem argumentos só pode ser usado dentro de um @forelse');
        }

        $empty = '$__empty_'.$this->forElseCounter--;

        return "<?php endforeach; \$__env->popLoop(); \$loop = \$__env->getLastLoop(); if ({$empty}): ?>";
    }

    protected function compileEndforelse(string $expression): string
    {
        return '<?php endif; ?>';
    }

    protected function compileFor(string $expression): string
    {
        return "<?php for({$expression}): ?>";
    }

    protected function compileEndfor(string $expression): string
    {
        return '<?php endfor; ?>';
    }

    protected function compileWhile(string $expression): string
    {
        return "<?php while({$expression}): ?>";
    }

    protected function compileEndwhile(string $expression): string
    {
        return '<?php endwhile; ?>';
    }

    protected function compileBreak(string $expression): string
    {
        return trim($expression) === '' ? '<?php break; ?>' : "<?php if({$expression}) break; ?>";
    }

    protected function compileContinue(string $expression): string
    {
        return trim($expression) === '' ? '<?php continue; ?>' : "<?php if({$expression}) continue; ?>";
    }

    /**
     * @return array{0: string, 1: string} [coleção, variáveis]
     */
    protected function parseLoop(string $expression, string $directive): array
    {
        if (preg_match('/^(.+)\s+as\s+(.+)$/is', trim($expression), $m) !== 1) {
            $this->fail("A directiva @{$directive} espera '\$items as \$item'");
        }

        return [trim($m[1]), trim($m[2])];
    }
}
