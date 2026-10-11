<?php

declare(strict_types=1);

namespace Slendie\View\Compiler\Directives;

/**
 * Directiva vite — nesta peça compila para o helper `vite()` (stub); a peça 09 liga-o à classe Vite real.
 */
trait Assets
{
    protected function compileVite(string $expression): string
    {
        return "<?php echo vite({$expression}); ?>";
    }
}
