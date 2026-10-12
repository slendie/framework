<?php

declare(strict_types=1);

namespace Slendie\Console;

use InvalidArgumentException;

/** Argumentos ou opções inválidos na linha de comandos (a mensagem inclui o modo de uso). */
class InvalidInputException extends InvalidArgumentException
{
}
