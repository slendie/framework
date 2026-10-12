<?php

declare(strict_types=1);

namespace Slendie\Console;

use InvalidArgumentException;

class CommandNotFoundException extends InvalidArgumentException
{
    /**
     * @param  list<string>  $alternatives  comandos parecidos
     */
    public function __construct(string $message, protected array $alternatives = [])
    {
        parent::__construct($message);
    }

    /** @return list<string> */
    public function alternatives(): array
    {
        return $this->alternatives;
    }
}
