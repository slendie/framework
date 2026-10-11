<?php

declare(strict_types=1);

namespace Slendie\View\Compiler\Directives;

/**
 * `{{ }}` e `{!! !!}` são tratados no pipeline do compilador (precisam de preservar linhas); aqui fica @json.
 */
trait Echos
{
    /**
     * `@json($dados, $flags = 0, $profundidade = 512)`. Os flags HEX_* são sempre aplicados: o resultado é seguro
     * dentro de `<script>` e de atributos HTML.
     */
    protected function compileJson(string $expression): string
    {
        $arguments = $this->splitArguments($expression);

        $data = $arguments[0];
        $flags = $arguments[1] ?? '0';
        $depth = $arguments[2] ?? '512';

        return "<?php echo json_encode({$data}, ({$flags}) | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT, {$depth}); ?>";
    }
}
