<?php

declare(strict_types=1);

namespace Slendie\View;

use Countable;
use Stringable;

/**
 * `$errors` nas views. Versão simples da peça 03; a peça 08 substitui-a pelo MessageBag.
 */
class ViewErrorBag implements Countable
{
    /** @var array<string, list<string>> */
    protected array $messages = [];

    /**
     * @param  array<array-key, mixed>  $messages  campo => mensagem|lista de mensagens (bag "default")
     * @param  array<string, ViewErrorBag>  $bags  outras bags, por nome
     */
    public function __construct(array $messages = [], protected array $bags = [])
    {
        foreach ($messages as $field => $value) {
            $this->messages[(string) $field] = array_values(array_map(
                static fn (mixed $m): string => is_scalar($m) || $m instanceof Stringable ? (string) $m : '',
                is_array($value) ? $value : [$value],
            ));
        }
    }

    /**
     * Constrói a partir do flash `errors` da sessão: [bag => [campo => mensagens]] (ver RedirectResponse::withErrors).
     *
     * @param  array<array-key, mixed>  $errors
     */
    public static function fromSession(array $errors): self
    {
        $default = $errors['default'] ?? [];
        $bags = [];

        foreach ($errors as $name => $messages) {
            if ($name !== 'default' && is_array($messages)) {
                $bags[(string) $name] = new self($messages);
            }
        }

        return new self(is_array($default) ? $default : [], $bags);
    }

    /** As directivas e os métodos usam a bag "default"; as outras acedem-se por aqui. */
    public function getBag(string $name = 'default'): self
    {
        return $name === 'default' ? $this : ($this->bags[$name] ?? new self());
    }

    public function has(string $field): bool
    {
        return ($this->messages[$field] ?? []) !== [];
    }

    public function first(?string $field = null, string $default = ''): string
    {
        if ($field === null) {
            foreach ($this->messages as $messages) {
                if ($messages !== []) {
                    return $messages[0];
                }
            }

            return $default;
        }

        return $this->messages[$field][0] ?? $default;
    }

    /** @return list<string> */
    public function get(string $field): array
    {
        return $this->messages[$field] ?? [];
    }

    /** @return list<string> */
    public function all(): array
    {
        return array_merge([], ...array_values($this->messages));
    }

    public function any(): bool
    {
        return $this->all() !== [];
    }

    public function isEmpty(): bool
    {
        return ! $this->any();
    }

    public function count(): int
    {
        return count($this->all());
    }

    /** @return array<string, list<string>> */
    public function toArray(): array
    {
        return $this->messages;
    }
}
