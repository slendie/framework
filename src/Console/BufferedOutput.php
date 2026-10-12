<?php

declare(strict_types=1);

namespace Slendie\Console;

/**
 * Saída em memória: testes e `Command::callSilently()`. STDOUT e STDERR vão para o mesmo buffer.
 */
class BufferedOutput extends Output
{
    private string $buffer = '';

    public function __construct(bool $decorated = false, int $verbosity = self::VERBOSITY_NORMAL)
    {
        parent::__construct($decorated, $verbosity);

        $this->setUnicode(true);
    }

    /** Devolve o que foi escrito (e, por omissão, esvazia o buffer). */
    public function fetch(bool $clear = true): string
    {
        $contents = $this->buffer;

        if ($clear) {
            $this->buffer = '';
        }

        return $contents;
    }

    protected function doWrite(string $text, bool $stderr): void
    {
        $this->buffer .= $text;
    }
}
