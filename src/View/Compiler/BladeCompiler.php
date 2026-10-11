<?php

declare(strict_types=1);

namespace Slendie\View\Compiler;

use RuntimeException;
use Slendie\View\CompileException;
use Slendie\View\Compiler\Directives\Assets;
use Slendie\View\Compiler\Directives\Conditionals;
use Slendie\View\Compiler\Directives\Echos;
use Slendie\View\Compiler\Directives\Forms;
use Slendie\View\Compiler\Directives\Layouts;
use Slendie\View\Compiler\Directives\Loops;
use Slendie\View\Compiler\Directives\Misc;

/**
 * Compila templates `.blade.php` em PHP nativo.
 *
 * Princípio de desenho: **o PHP compilado tem exactamente as mesmas linhas que o template**. Nenhuma transformação
 * acrescenta ou remove quebras de linha, por isso a linha de uma excepção no ficheiro compilado é a linha original
 * (o Engine usa-a directamente) e os erros de compilação sabem em que linha estão.
 */
class BladeCompiler
{
    use Assets;
    use Conditionals;
    use Echos;
    use Forms;
    use Layouts;
    use Loops;
    use Misc;

    /** At-rules de CSS/JS que não são directivas e passam intactas. */
    protected const PASSTHROUGH = [
        'media', 'import', 'charset', 'supports', 'page', 'layer', 'tailwind', 'apply', 'keyframes', 'container',
        'property', 'scope', 'namespace', 'theme', 'utility', 'source', 'variant', 'plugin', 'config', 'reference',
        'screen', 'font', 'counter', 'view', 'position', 'starting', 'custom',
    ];

    /** Nomes reservados: o método existe mas não é uma directiva. */
    protected const RESERVED = ['string'];

    /** Directivas que não fazem sentido sem expressão. */
    protected const NEEDS_EXPRESSION = [
        'if', 'elseif', 'unless', 'isset', 'foreach', 'forelse', 'for', 'while', 'switch', 'case', 'extends', 'section',
        'yield', 'include', 'includeIf', 'includeWhen', 'includeUnless', 'includeFirst', 'push', 'prepend', 'stack',
        'hasSection', 'sectionMissing', 'error', 'method', 'json', 'inject', 'class', 'checked', 'selected', 'disabled',
        'readonly', 'required', 'dd', 'dump', 'env', 'vite',
    ];

    /** @var array<string, callable> */
    protected array $customDirectives = [];

    /** @var array<string, string> token => PHP/texto final */
    protected array $rawBlocks = [];

    /** @var list<string> código acrescentado no fim do ficheiro (o @extends) */
    protected array $footer = [];

    protected ?string $currentPath = null;

    protected int $currentLine = 1;

    protected int $forElseCounter = 0;

    protected int $errorCounter = 0;

    protected int $onceCounter = 0;

    /** @var list<bool> true enquanto o próximo @case é o primeiro do @switch */
    protected array $switchStack = [];

    public function __construct(protected string $cachePath, protected bool $debug = false)
    {
    }

    // -------------------------------------------------------------------------
    // Ficheiros e cache
    // -------------------------------------------------------------------------

    public function getCachePath(): string
    {
        return $this->cachePath;
    }

    public function getCompiledPath(string $path): string
    {
        return rtrim($this->cachePath, '/\\').'/'.sha1($path).'.php';
    }

    /**
     * Recompilar quando a fonte é mais recente do que a cache; com debug, também se o conteúdo mudou.
     */
    public function isExpired(string $path): bool
    {
        $compiled = $this->getCompiledPath($path);

        clearstatcache(false, $path);
        clearstatcache(false, $compiled);

        if (! is_file($compiled) || (int) filemtime($path) >= (int) filemtime($compiled)) {
            return true;
        }

        if ($this->debug) {
            $source = @file_get_contents($path);

            return $source === false || $this->compiledHash($compiled) !== sha1($source);
        }

        return false;
    }

    public function compile(string $path): void
    {
        $contents = @file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException("Não foi possível ler a view [{$path}].");
        }

        $this->currentPath = $path;

        try {
            $compiled = $this->compileString($contents).'<?php /**PATH:'.$path.'|HASH:'.sha1($contents).'**/ ?>';
        } finally {
            $this->currentPath = null;
        }

        $this->write($this->getCompiledPath($path), $compiled);
    }

    /**
     * Apaga todas as views compiladas (usado por `view:clear`). Devolve quantos ficheiros removeu.
     */
    public function clearCache(): int
    {
        $removed = 0;

        foreach (glob(rtrim($this->cachePath, '/\\').'/*.php') ?: [] as $file) {
            if (@unlink($file)) {
                $removed++;
            }
        }

        return $removed;
    }

    protected function compiledHash(string $compiled): ?string
    {
        $size = (int) filesize($compiled);
        $tail = @file_get_contents($compiled, false, null, max(0, $size - 512));

        if ($tail !== false && preg_match('/\|HASH:([0-9a-f]{40})\*\*\/ \?>$/', $tail, $m) === 1) {
            return $m[1];
        }

        return null;
    }

    protected function write(string $file, string $contents): void
    {
        $directory = dirname($file);

        if (! is_dir($directory) && ! @mkdir($directory, 0777, true) && ! is_dir($directory)) {
            throw new RuntimeException("Não foi possível criar a pasta de views compiladas [{$directory}].");
        }

        $temporary = $file.'.'.bin2hex(random_bytes(4)).'.tmp';

        if (@file_put_contents($temporary, $contents) === false) {
            throw new RuntimeException("Não foi possível escrever a view compilada em [{$directory}].");
        }

        if (! @rename($temporary, $file)) {
            @unlink($temporary);

            if (@file_put_contents($file, $contents, LOCK_EX) === false) {
                throw new RuntimeException("Não foi possível escrever a view compilada [{$file}].");
            }
        }
    }

    // -------------------------------------------------------------------------
    // Directivas personalizadas
    // -------------------------------------------------------------------------

    /**
     * Regista `@nome`. O handler recebe a expressão (sem os parênteses exteriores; '' se não houver) e devolve PHP.
     */
    public function directive(string $name, callable $handler): void
    {
        if (preg_match('/^\w+(?:::\w+)?$/', $name) !== 1) {
            throw new \InvalidArgumentException("O nome de directiva [{$name}] só pode ter letras, números e _.");
        }

        $this->customDirectives[$name] = $handler;
    }

    /** @return array<string, callable> */
    public function getCustomDirectives(): array
    {
        return $this->customDirectives;
    }

    // -------------------------------------------------------------------------
    // Pipeline
    // -------------------------------------------------------------------------

    public function compileString(string $value): string
    {
        $this->rawBlocks = [];
        $this->footer = [];
        $this->forElseCounter = 0;
        $this->errorCounter = 0;
        $this->onceCounter = 0;
        $this->switchStack = [];

        $value = $this->processVerbatim($value);
        $value = $this->processPhpBlocks($value);
        $value = $this->processRawPhp($value);
        $value = $this->processComments($value);
        $value = $this->processEchos($value);
        $value = $this->processStatements($value);
        $value = strtr($value, $this->rawBlocks);

        $this->rawBlocks = [];

        return $value.implode('', $this->footer);
    }

    /**
     * Guarda um troço já final e devolve um marcador com o mesmo número de linhas do original.
     */
    protected function stash(string $original, string $replacement): string
    {
        $token = "\0".count($this->rawBlocks)."\0".str_repeat("\n", substr_count($original, "\n"));

        $this->rawBlocks[$token] = $replacement;

        return $token;
    }

    protected function processVerbatim(string $value): string
    {
        return (string) preg_replace_callback(
            '/(?<!@)@verbatim(.*?)@endverbatim/s',
            fn (array $m): string => $this->stash($m[0], $m[1]),
            $value,
        );
    }

    protected function processPhpBlocks(string $value): string
    {
        return (string) preg_replace_callback(
            '/(?<!@)@php(?![ \t]*\()(.*?)@endphp/s',
            fn (array $m): string => $this->stash($m[0], '<?php '.$m[1].' ?>'),
            $value,
        );
    }

    protected function processRawPhp(string $value): string
    {
        return (string) preg_replace_callback(
            '/<\?(?:php|=).*?(?:\?>|\z)/s',
            fn (array $m): string => $this->stash($m[0], $m[0]),
            $value,
        );
    }

    protected function processComments(string $value): string
    {
        return (string) preg_replace_callback(
            '/\{\{--(.*?)--\}\}(\r?\n)?/s',
            function (array $m): string {
                $trailing = $m[2] ?? '';
                $php = '<?php'.str_repeat("\n", substr_count($m[1], "\n")).$this->newlineEcho($trailing).' ?>';

                return $this->stash('{{--'.$m[1].'--}}', $php).$trailing;
            },
            $value,
        );
    }

    protected function processEchos(string $value): string
    {
        $value = (string) preg_replace_callback(
            '/(@)?\{!!(\s*)(.+?)(\s*)!!\}(\r?\n)?/s',
            fn (array $m): string => $this->replaceEcho($m, false),
            $value,
        );

        return (string) preg_replace_callback(
            '/(@)?\{\{(\s*)(.+?)(\s*)\}\}(\r?\n)?/s',
            fn (array $m): string => $this->replaceEcho($m, true),
            $value,
        );
    }

    /**
     * @param  array<int|string, string>  $m
     */
    protected function replaceEcho(array $m, bool $escape): string
    {
        $trailing = $m[5] ?? '';
        $original = substr($m[0], 0, strlen($m[0]) - strlen($trailing));

        // @{{ ... }} → literal (para frameworks de JS)
        if (($m[1] ?? '') === '@') {
            $literal = substr($original, 1);

            return $this->stash($literal, $literal).$trailing;
        }

        $newline = $trailing === '' ? '' : ', '.$this->newlineLiteral($trailing);
        $php = $escape
            ? "<?php echo e({$m[2]}{$m[3]}{$m[4]}){$newline}; ?>"
            : "<?php echo {$m[2]}{$m[3]}{$m[4]}{$newline}; ?>";

        return $this->stash($original, $php).$trailing;
    }

    /**
     * Depois de `?>` o PHP engole uma quebra de linha; reemite-a para o HTML ficar como no template
     * sem acrescentar linhas ao ficheiro compilado.
     */
    protected function newlineLiteral(string $newline): string
    {
        return $newline === "\r\n" ? '"\r\n"' : '"\n"';
    }

    protected function newlineEcho(string $newline): string
    {
        return $newline === '' ? '' : ' echo '.$this->newlineLiteral($newline).';';
    }

    protected function processStatements(string $value): string
    {
        if (preg_match_all('/\B@(@?\w+(?:::\w+)?)([ \t]*)(?<args>\((?:[^()]++|(?&args))*\))?/', $value, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE) < 1) {
            return $value;
        }

        $output = '';
        $cursor = 0;

        foreach ($matches as $m) {
            [$whole, $offset] = $m[0];
            $name = $m[1][0];

            $output .= substr($value, $cursor, $offset - $cursor);
            $cursor = $offset + strlen($whole);

            // @@nome → @nome literal
            if (str_starts_with($name, '@')) {
                $output .= substr($whole, 1);

                continue;
            }

            // @font-face e afins: o nome de uma directiva nunca tem hífen.
            if (($value[$m[1][1] + strlen($name)] ?? '') === '-') {
                $output .= $whole;

                continue;
            }

            $hasArgs = isset($m['args']);
            $expression = $hasArgs ? substr($m['args'][0], 1, -1) : '';

            $this->currentLine = substr_count($value, "\n", 0, $offset) + 1;

            $handler = $this->resolveDirective($name);

            if ($handler === null) {
                if (! in_array($name, self::PASSTHROUGH, true)) {
                    $this->fail("Directiva @{$name} desconhecida");
                }

                $output .= $whole;

                continue;
            }

            if (in_array($name, self::NEEDS_EXPRESSION, true) && trim($expression) === '') {
                $this->fail("A directiva @{$name} precisa de uma expressão: @{$name}(...)");
            }

            // Sem argumentos, o espaço depois da directiva é texto e mantém-se.
            $output .= $handler($expression).($hasArgs ? '' : $m[2][0]);
        }

        return $output.substr($value, $cursor);
    }

    /**
     * @return (callable(string): string)|null
     */
    protected function resolveDirective(string $name): ?callable
    {
        if (isset($this->customDirectives[$name])) {
            $custom = $this->customDirectives[$name];

            return static fn (string $expression): string => (string) $custom($expression);
        }

        $method = 'compile'.ucfirst($name);

        if (! in_array(strtolower($name), self::RESERVED, true) && preg_match('/^[A-Za-z]\w*$/', $name) === 1 && method_exists($this, $method)) {
            return fn (string $expression): string => $this->{$method}($expression);
        }

        return null;
    }

    protected function fail(string $message): never
    {
        $where = $this->currentPath ?? 'template';

        throw new CompileException("{$message} em {$where}:{$this->currentLine}", $this->currentPath, $this->currentLine);
    }

    /**
     * Divide uma lista de argumentos PHP pelas vírgulas de nível 0 (respeita parênteses, arrays e strings).
     *
     * @return list<string>
     */
    protected function splitArguments(string $expression): array
    {
        $parts = [];
        $depth = 0;
        $quote = null;
        $current = '';
        $length = strlen($expression);

        for ($i = 0; $i < $length; $i++) {
            $char = $expression[$i];

            if ($quote !== null) {
                $current .= $char;

                if ($char === '\\' && $i + 1 < $length) {
                    $current .= $expression[++$i];
                } elseif ($char === $quote) {
                    $quote = null;
                }

                continue;
            }

            if ($char === '"' || $char === "'") {
                $quote = $char;
            } elseif ($char === '(' || $char === '[' || $char === '{') {
                $depth++;
            } elseif ($char === ')' || $char === ']' || $char === '}') {
                $depth--;
            } elseif ($char === ',' && $depth === 0) {
                $parts[] = trim($current);
                $current = '';

                continue;
            }

            $current .= $char;
        }

        if (trim($current) !== '' || $parts !== []) {
            $parts[] = trim($current);
        }

        return $parts;
    }
}
