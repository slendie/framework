<?php

declare(strict_types=1);

namespace Slendie\Exceptions;

use Throwable;

/**
 * Excepção com código de estado HTTP; o Handler converte-a na resposta correspondente.
 */
class HttpException extends SlendieException
{
    /**
     * @param  array<string, string>  $headers
     */
    public function __construct(
        protected int $statusCode,
        string $message = '',
        ?Throwable $previous = null,
        protected array $headers = [],
        int $code = 0,
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * @param  array<string, string>  $headers
     */
    public function setHeaders(array $headers): static
    {
        $this->headers = $headers;

        return $this;
    }
}
