<?php

declare(strict_types=1);

namespace Slendie\Validation;

use Countable;
use JsonSerializable;
use Slendie\Contracts\Arrayable;
use Stringable;

/**
 * Mensagens de erro por campo. É o `$errors` das views.
 */
class MessageBag implements Arrayable, Countable, JsonSerializable, Stringable
{
    /** @var array<string, list<string>> */
    protected array $messages = [];

    /**
     * @param  array<array-key, mixed>  $messages  campo => mensagem|lista de mensagens
     */
    public function __construct(array $messages = [])
    {
        foreach ($messages as $key => $value) {
            foreach (is_array($value) ? $value : [$value] as $message) {
                $this->add((string) $key, is_scalar($message) || $message instanceof Stringable ? (string) $message : '');
            }
        }
    }

    /** Acrescenta uma mensagem (sem duplicar a mesma mensagem no mesmo campo). */
    public function add(string $key, string $message): static
    {
        if (! in_array($message, $this->messages[$key] ?? [], true)) {
            $this->messages[$key][] = $message;
        }

        return $this;
    }

    /**
     * @param  array<array-key, mixed>|self  $messages
     */
    public function merge(array|self $messages): static
    {
        $messages = $messages instanceof self ? $messages->getMessages() : $messages;

        foreach ($messages as $key => $value) {
            foreach (is_array($value) ? $value : [$value] as $message) {
                $this->add((string) $key, is_scalar($message) || $message instanceof Stringable ? (string) $message : '');
            }
        }

        return $this;
    }

    /**
     * Existe pelo menos uma mensagem para a chave (ou para todas as chaves dadas). Aceita wildcards (`items.*`).
     *
     * @param  string|list<string>|null  $key
     */
    public function has(string|array|null $key): bool
    {
        if ($key === null) {
            return $this->any();
        }

        foreach ((array) $key as $k) {
            if ($this->get($k) === []) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  string|list<string>  $keys
     */
    public function hasAny(string|array $keys): bool
    {
        foreach ((array) $keys as $key) {
            if ($this->has($key)) {
                return true;
            }
        }

        return false;
    }

    /** Primeira mensagem da chave (ou do bag inteiro sem chave). */
    public function first(?string $key = null, string $format = ':message'): string
    {
        $messages = $key === null ? $this->all($format) : $this->get($key, $format);

        return $messages[0] ?? '';
    }

    /**
     * @return list<string>
     */
    public function get(string $key, string $format = ':message'): array
    {
        if (isset($this->messages[$key])) {
            return $this->formatAll($this->messages[$key], $format);
        }

        if (! str_contains($key, '*')) {
            return [];
        }

        $pattern = '/^'.str_replace('\*', '[^.]+', preg_quote($key, '/')).'$/';
        $found = [];

        foreach ($this->messages as $field => $messages) {
            if (preg_match($pattern, (string) $field) === 1) {
                array_push($found, ...$this->formatAll($messages, $format));
            }
        }

        return $found;
    }

    /**
     * @return list<string>
     */
    public function all(string $format = ':message'): array
    {
        $all = [];

        foreach ($this->messages as $messages) {
            array_push($all, ...$this->formatAll($messages, $format));
        }

        return $all;
    }

    /** @return list<string> */
    public function keys(): array
    {
        return array_map('strval', array_keys($this->messages));
    }

    public function isEmpty(): bool
    {
        return $this->messages === [];
    }

    public function isNotEmpty(): bool
    {
        return ! $this->isEmpty();
    }

    public function any(): bool
    {
        return $this->isNotEmpty();
    }

    /** Número total de mensagens. */
    public function count(): int
    {
        return count($this->all());
    }

    /** @return array<string, list<string>> */
    public function getMessages(): array
    {
        return $this->messages;
    }

    /** @return array<string, list<string>> */
    public function toArray(): array
    {
        return $this->messages;
    }

    /** @return array<string, list<string>> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function toJson(int $options = 0): string
    {
        return json_encode($this->toArray(), $options | JSON_THROW_ON_ERROR);
    }

    public function __toString(): string
    {
        return $this->toJson();
    }

    /**
     * @param  list<string>  $messages
     * @return list<string>
     */
    protected function formatAll(array $messages, string $format): array
    {
        return array_map(static fn (string $message): string => str_replace(':message', $message, $format), $messages);
    }
}
