<?php

declare(strict_types=1);

namespace Slendie\Exceptions;

use Throwable;

class MethodNotAllowedHttpException extends HttpException
{
    /**
     * @param  list<string>  $allow  métodos permitidos (vão para o header Allow)
     * @param  array<string, string>  $headers
     */
    public function __construct(array $allow, string $message = '', ?Throwable $previous = null, array $headers = [], int $code = 0)
    {
        $headers['Allow'] = strtoupper(implode(', ', $allow));

        parent::__construct(405, $message, $previous, $headers, $code);
    }
}
