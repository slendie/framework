<?php

declare(strict_types=1);

namespace Slendie\Http;

use InvalidArgumentException;

/**
 * Value object de uma cookie a enviar na resposta.
 */
final readonly class Cookie
{
    public string $sameSite;

    /**
     * @param  int  $expires  timestamp Unix; 0 = cookie de sessão; no passado = apagar
     */
    public function __construct(
        public string $name,
        public string $value = '',
        public int $expires = 0,
        public string $path = '/',
        public ?string $domain = null,
        public bool $secure = false,
        public bool $httpOnly = true,
        string $sameSite = 'lax',
    ) {
        if ($name === '' || preg_match('/[=,; \t\r\n\013\014]/', $name) === 1) {
            throw new InvalidArgumentException("Nome de cookie inválido: [{$name}].");
        }

        $sameSite = strtolower($sameSite);

        if (! in_array($sameSite, ['lax', 'strict', 'none'], true)) {
            throw new InvalidArgumentException("SameSite inválido: [{$sameSite}].");
        }

        $this->sameSite = $sameSite;
    }

    /**
     * Cookie que expira daqui a $minutes minutos.
     */
    public static function minutes(string $name, string $value, int $minutes, string $path = '/', ?string $domain = null, bool $secure = false, bool $httpOnly = true, string $sameSite = 'lax'): self
    {
        return new self($name, $value, $minutes > 0 ? time() + $minutes * 60 : 0, $path, $domain, $secure, $httpOnly, $sameSite);
    }

    public static function forget(string $name, string $path = '/', ?string $domain = null): self
    {
        return new self($name, '', time() - 3600, $path, $domain);
    }

    public function isExpired(): bool
    {
        return $this->expires !== 0 && $this->expires < time();
    }

    public function toHeaderString(): string
    {
        $parts = [$this->name.'='.rawurlencode($this->value)];

        if ($this->expires !== 0) {
            $parts[] = 'Expires='.gmdate('D, d M Y H:i:s', $this->expires).' GMT';
            $parts[] = 'Max-Age='.max(0, $this->expires - time());
        }

        $parts[] = 'Path='.$this->path;

        if ($this->domain !== null && $this->domain !== '') {
            $parts[] = 'Domain='.$this->domain;
        }

        if ($this->secure || $this->sameSite === 'none') {
            $parts[] = 'Secure';
        }

        if ($this->httpOnly) {
            $parts[] = 'HttpOnly';
        }

        $parts[] = 'SameSite='.ucfirst($this->sameSite);

        return implode('; ', $parts);
    }

    public function __toString(): string
    {
        return $this->toHeaderString();
    }
}
