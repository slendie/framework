<?php

declare(strict_types=1);

namespace Slendie\View\Compiler\Directives;

/**
 * Directivas `@vite([...])` e `@viteReactRefresh` — delegam em `Slendie\Support\Vite`.
 */
trait Assets
{
    protected function compileVite(string $expression): string
    {
        return "<?php echo app(\Slendie\Support\Vite::class)({$expression}); ?>";
    }

    protected function compileViteReactRefresh(string $expression): string
    {
        return '<?php echo app(\Slendie\Support\Vite::class)->reactRefresh(); ?>';
    }
}
