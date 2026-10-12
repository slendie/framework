<?php

declare(strict_types=1);

namespace Slendie\Console;

/**
 * Saída da consola: STDOUT/STDERR, estilos ANSI por etiquetas (`<info>texto</info>`), verbosidade e tabelas.
 *
 * Sem ANSI (pipe, `NO_COLOR`, `--no-ansi`) as etiquetas são simplesmente removidas.
 */
class Output
{
    public const int VERBOSITY_QUIET = 0;

    public const int VERBOSITY_NORMAL = 1;

    public const int VERBOSITY_VERBOSE = 2;

    public const int VERBOSITY_VERY_VERBOSE = 3;

    /** @var array<string, string> etiqueta => código SGR */
    private const array STYLES = [
        'info' => '32',
        'comment' => '33',
        'question' => '36',
        'error' => '37;41',
        'warn' => '33',
        'muted' => '90',
        'bold' => '1',
        'badge-info' => '30;46',
        'badge-success' => '30;42',
        'badge-warn' => '30;43',
        'badge-error' => '37;41',
    ];

    private const string TAG_PATTERN = '#<([a-z][a-z-]*)>(.*?)</\1>#s';

    private const string ANSI_PATTERN = '/\e\[[0-9;]*m/';

    /** @var resource|null */
    private $stdout;

    /** @var resource|null */
    private $stderr;

    private bool $decorated;

    private bool $unicode;

    /**
     * @param  resource|null  $stdout
     * @param  resource|null  $stderr
     */
    public function __construct(
        ?bool $decorated = null,
        protected int $verbosity = self::VERBOSITY_NORMAL,
        $stdout = null,
        $stderr = null,
    ) {
        $this->stdout = $stdout;
        $this->stderr = $stderr;
        $this->decorated = $decorated ?? self::detectAnsi($this->stdoutStream());
        // Consolas Windows antigas não descodificam UTF-8: as tabelas usam ASCII por omissão.
        $this->unicode = PHP_OS_FAMILY !== 'Windows';
    }

    /**
     * Deve a saída ter cores? Respeita `NO_COLOR`, `TERM=dumb` e a existência de um terminal.
     *
     * @param  resource|null  $stream
     */
    public static function detectAnsi($stream): bool
    {
        $noColor = getenv('NO_COLOR');

        if ($noColor !== false && $noColor !== '') {
            return false;
        }

        if (getenv('TERM') === 'dumb' || ! is_resource($stream) || ! stream_isatty($stream)) {
            return false;
        }

        if (PHP_OS_FAMILY === 'Windows') {
            return function_exists('sapi_windows_vt100_support') && @sapi_windows_vt100_support($stream, true);
        }

        return true;
    }

    // -------------------------------------------------------------------------
    // Configuração
    // -------------------------------------------------------------------------

    public function setDecorated(bool $decorated): static
    {
        $this->decorated = $decorated;

        return $this;
    }

    public function isDecorated(): bool
    {
        return $this->decorated;
    }

    public function setUnicode(bool $unicode): static
    {
        $this->unicode = $unicode;

        return $this;
    }

    public function isUnicode(): bool
    {
        return $this->unicode;
    }

    public function setVerbosity(int $verbosity): static
    {
        $this->verbosity = max(self::VERBOSITY_QUIET, min(self::VERBOSITY_VERY_VERBOSE, $verbosity));

        return $this;
    }

    public function getVerbosity(): int
    {
        return $this->verbosity;
    }

    public function isQuiet(): bool
    {
        return $this->verbosity === self::VERBOSITY_QUIET;
    }

    public function isVerbose(): bool
    {
        return $this->verbosity >= self::VERBOSITY_VERBOSE;
    }

    public function isVeryVerbose(): bool
    {
        return $this->verbosity >= self::VERBOSITY_VERY_VERBOSE;
    }

    /** Largura do terminal (`COLUMNS`), limitada a [40, 120]; 80 se desconhecida. */
    public function terminalWidth(): int
    {
        $columns = getenv('COLUMNS');

        return is_string($columns) && ctype_digit($columns) ? max(40, min(120, (int) $columns)) : 80;
    }

    // -------------------------------------------------------------------------
    // Escrita
    // -------------------------------------------------------------------------

    /**
     * Escreve em STDOUT, se a verbosidade actual for pelo menos `$minVerbosity`.
     */
    public function write(string $text, int $minVerbosity = self::VERBOSITY_NORMAL): void
    {
        if ($this->verbosity >= $minVerbosity) {
            $this->doWrite($this->format($text), false);
        }
    }

    public function writeln(string $text = '', int $minVerbosity = self::VERBOSITY_NORMAL): void
    {
        $this->write($text.PHP_EOL, $minVerbosity);
    }

    /** Escreve em STDERR (também em modo silencioso). */
    public function errorln(string $text = ''): void
    {
        $this->doWrite($this->format($text).PHP_EOL, true);
    }

    /**
     * Desenha uma tabela com larguras automáticas (medidas com `mb_strwidth`, ignorando estilos).
     *
     * @param  list<string>  $headers
     * @param  list<array<array-key, mixed>>  $rows
     */
    public function table(array $headers, array $rows): void
    {
        $matrix = array_map(
            static fn (array $row): array => array_map(
                static fn (mixed $cell): string => is_scalar($cell) ? (string) $cell : '',
                array_values($row),
            ),
            $rows,
        );

        $columns = max(count($headers), ...array_map('count', $matrix ?: [[]]));
        $widths = array_fill(0, $columns, 0);

        foreach ([$headers, ...$matrix] as $row) {
            foreach (array_values($row) as $i => $cell) {
                $widths[$i] = max($widths[$i], $this->width($cell));
            }
        }

        [$h, $v, $tl, $tm, $tr, $ml, $mm, $mr, $bl, $bm, $br] = $this->unicode
            ? ['─', '│', '┌', '┬', '┐', '├', '┼', '┤', '└', '┴', '┘']
            : ['-', '|', '+', '+', '+', '+', '+', '+', '+', '+', '+'];

        $rule = static fn (string $left, string $mid, string $right): string => $left
            .implode($mid, array_map(static fn (int $w): string => str_repeat($h, $w + 2), $widths))
            .$right;

        $line = function (array $cells, ?string $style = null) use ($widths, $v): string {
            $out = [];

            foreach ($widths as $i => $width) {
                $cell = (string) ($cells[$i] ?? '');
                $padding = str_repeat(' ', max(0, $width - $this->width($cell)));
                $out[] = ' '.($style !== null ? "<{$style}>{$cell}</{$style}>" : $cell).$padding.' ';
            }

            return $v.implode($v, $out).$v;
        };

        $this->writeln($rule($tl, $tm, $tr));

        if ($headers !== []) {
            $this->writeln($line($headers, 'info'));
            $this->writeln($rule($ml, $mm, $mr));
        }

        foreach ($matrix as $row) {
            $this->writeln($line($row));
        }

        $this->writeln($rule($bl, $bm, $br));
    }

    // -------------------------------------------------------------------------
    // Formatação
    // -------------------------------------------------------------------------

    /** Aplica (ou remove, sem ANSI) as etiquetas de estilo. */
    public function format(string $text): string
    {
        if (! str_contains($text, '</')) {
            return $text;
        }

        return (string) preg_replace_callback(self::TAG_PATTERN, function (array $m): string {
            $code = self::STYLES[$m[1]] ?? null;

            if ($code === null) {
                return $m[0];
            }

            return $this->decorated ? "\e[{$code}m{$m[2]}\e[0m" : $m[2];
        }, $text);
    }

    /** Texto sem etiquetas de estilo nem sequências ANSI. */
    public function strip(string $text): string
    {
        $text = (string) preg_replace_callback(self::TAG_PATTERN, static fn (array $m): string => isset(self::STYLES[$m[1]]) ? $m[2] : $m[0], $text);

        return (string) preg_replace(self::ANSI_PATTERN, '', $text);
    }

    /** Largura visível (colunas) do texto, sem estilos. */
    public function width(string $text): int
    {
        return mb_strwidth($this->strip($text), 'UTF-8');
    }

    // -------------------------------------------------------------------------
    // Internos
    // -------------------------------------------------------------------------

    protected function doWrite(string $text, bool $stderr): void
    {
        $stream = $stderr ? $this->stderrStream() : $this->stdoutStream();

        if ($stream !== null) {
            // Um pipe fechado (`| head`) não deve rebentar o comando.
            @fwrite($stream, $text);
            @fflush($stream); // o `serve` pode ser morto sem passar pelo shutdown
        }
    }

    /** @return resource|null */
    private function stdoutStream()
    {
        if ($this->stdout === null) {
            $stream = @fopen('php://stdout', 'w');
            $this->stdout = $stream === false ? null : $stream;
        }

        return $this->stdout;
    }

    /** @return resource|null */
    private function stderrStream()
    {
        if ($this->stderr === null) {
            $stream = @fopen('php://stderr', 'w');
            $this->stderr = $stream === false ? null : $stream;
        }

        return $this->stderr;
    }
}
