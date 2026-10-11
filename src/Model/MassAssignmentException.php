<?php

declare(strict_types=1);

namespace Slendie\Model;

use Slendie\Exceptions\SlendieException;

/**
 * Atributo não permitido por `$fillable`/`$guarded` (só em debug ou com `preventSilentlyDiscardingAttributes()`).
 */
class MassAssignmentException extends SlendieException
{
}
