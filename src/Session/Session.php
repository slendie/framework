<?php

declare(strict_types=1);

namespace Slendie\Session;

use SessionHandlerInterface;
use Slendie\Support\Arr;
use Slendie\Support\Str;

/**
 * Sessão de um pedido: atributos, flash data, token CSRF e input antigo.
 * Independente de $_SESSION: o id viaja numa cookie e os dados vivem num SessionHandlerInterface.
 */
class Session
{
    /** @var array<string, mixed> */
    protected array $attributes = [];

    protected bool $started = false;

    protected bool $dirty = false;

    protected string $id;

    public function __construct(protected string $name, protected SessionHandlerInterface $handler, ?string $id = null)
    {
        $this->id = $this->isValidId($id) ? $id : $this->generateId();
    }

    public function name(): string
    {
        return $this->name;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function setId(?string $id): static
    {
        $this->id = $this->isValidId($id) ? $id : $this->generateId();

        return $this;
    }

    public function handler(): SessionHandlerInterface
    {
        return $this->handler;
    }

    // -------------------------------------------------------------------------
    // Ciclo de vida
    // -------------------------------------------------------------------------

    public function start(): bool
    {
        $this->loadSession();

        if (! $this->has('_token')) {
            $this->regenerateToken();
        }

        $this->dirty = false;

        return $this->started = true;
    }

    public function isStarted(): bool
    {
        return $this->started;
    }

    protected function loadSession(): void
    {
        $data = $this->handler->read($this->id);

        if ($data === false || $data === '') {
            $this->attributes = [];

            return;
        }

        $decoded = @unserialize($data, ['allowed_classes' => false]);

        /** @var array<string, mixed> $attributes */
        $attributes = is_array($decoded) ? $decoded : [];
        $this->attributes = $attributes;
    }

    /**
     * Envelhece o flash data e grava.
     */
    public function save(): void
    {
        $this->ageFlashData();

        $this->handler->write($this->id, serialize($this->attributes));

        $this->dirty = false;
        $this->started = false;
    }

    /** Há alterações por gravar? */
    public function isDirty(): bool
    {
        return $this->dirty;
    }

    protected function ageFlashData(): void
    {
        /** @var list<string> $old */
        $old = $this->get('_flash.old', []);

        foreach ($old as $key) {
            $this->forget($key);
        }

        $this->put('_flash.old', $this->get('_flash.new', []));
        $this->put('_flash.new', []);
    }

    /** Novo id (a sessão mantém os dados); $destroy apaga os dados do id antigo. */
    public function regenerate(bool $destroy = false): bool
    {
        if ($destroy) {
            $this->handler->destroy($this->id);
        }

        $this->id = $this->generateId();
        $this->dirty = true;

        return true;
    }

    /** Apaga todos os dados e muda o id. */
    public function invalidate(): bool
    {
        $this->flush();

        return $this->regenerate(true);
    }

    // -------------------------------------------------------------------------
    // Atributos
    // -------------------------------------------------------------------------

    public function get(string $key, mixed $default = null): mixed
    {
        return Arr::get($this->attributes, $key, $default);
    }

    /**
     * @param  string|array<string, mixed>  $key
     */
    public function put(string|array $key, mixed $value = null): void
    {
        foreach (is_array($key) ? $key : [$key => $value] as $k => $v) {
            Arr::set($this->attributes, $k, $v);
        }

        $this->dirty = true;
    }

    /** Existe e não é null. */
    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    /** Existe (mesmo que seja null). */
    public function exists(string $key): bool
    {
        return Arr::has($this->attributes, $key);
    }

    public function forget(string $key): void
    {
        Arr::forget($this->attributes, $key);

        $this->dirty = true;
    }

    public function flush(): void
    {
        $this->attributes = [];
        $this->dirty = true;
    }

    public function pull(string $key, mixed $default = null): mixed
    {
        $value = $this->get($key, $default);
        $this->forget($key);

        return $value;
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        return $this->attributes;
    }

    /** Acrescenta um valor a um array da sessão. */
    public function push(string $key, mixed $value): void
    {
        $array = $this->get($key, []);
        $array = is_array($array) ? $array : [$array];
        $array[] = $value;

        $this->put($key, $array);
    }

    public function increment(string $key, int $amount = 1): int
    {
        $value = $this->get($key, 0);
        $value = (is_numeric($value) ? (int) $value : 0) + $amount;

        $this->put($key, $value);

        return $value;
    }

    public function decrement(string $key, int $amount = 1): int
    {
        return $this->increment($key, -$amount);
    }

    // -------------------------------------------------------------------------
    // Flash
    // -------------------------------------------------------------------------

    /** Disponível no próximo pedido apenas. */
    public function flash(string $key, mixed $value = true): void
    {
        $this->put($key, $value);
        $this->push('_flash.new', $key);
        $this->removeFromOldFlashData([$key]);
    }

    /** Disponível apenas no pedido actual. */
    public function now(string $key, mixed $value): void
    {
        $this->put($key, $value);
        $this->push('_flash.old', $key);
    }

    /** Mantém todo o flash data para mais um pedido. */
    public function reflash(): void
    {
        /** @var list<string> $old */
        $old = $this->get('_flash.old', []);

        $this->mergeNewFlashes($old);
        $this->put('_flash.old', []);
    }

    /**
     * Mantém chaves específicas de flash para mais um pedido.
     *
     * @param  string|list<string>  $keys
     */
    public function keep(string|array $keys): void
    {
        $keys = (array) $keys;

        $this->mergeNewFlashes($keys);
        $this->removeFromOldFlashData($keys);
    }

    /**
     * @param  list<string>  $keys
     */
    protected function mergeNewFlashes(array $keys): void
    {
        /** @var list<string> $values */
        $values = $this->get('_flash.new', []);

        $this->put('_flash.new', array_values(array_unique([...$values, ...$keys])));
    }

    /**
     * @param  list<string>  $keys
     */
    protected function removeFromOldFlashData(array $keys): void
    {
        /** @var list<string> $old */
        $old = $this->get('_flash.old', []);

        $this->put('_flash.old', array_values(array_diff($old, $keys)));
    }

    // -------------------------------------------------------------------------
    // Input antigo, token, URL anterior
    // -------------------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $input
     */
    public function flashInput(array $input): void
    {
        $this->flash('_old_input', $input);
    }

    public function getOldInput(?string $key = null, mixed $default = null): mixed
    {
        $input = $this->get('_old_input', []);
        $input = is_array($input) ? $input : [];

        return $key === null ? $input : Arr::get($input, $key, $default);
    }

    public function token(): string
    {
        $token = $this->get('_token');

        if (! is_string($token) || $token === '') {
            $this->regenerateToken();
            $token = (string) $this->get('_token');
        }

        return $token;
    }

    public function regenerateToken(): void
    {
        $this->put('_token', Str::random(40));
    }

    public function previousUrl(): ?string
    {
        $url = $this->get('_previous.url');

        return is_string($url) ? $url : null;
    }

    public function setPreviousUrl(string $url): void
    {
        $this->put('_previous.url', $url);
    }

    // -------------------------------------------------------------------------

    /**
     * @phpstan-assert-if-true string $id
     */
    protected function isValidId(?string $id): bool
    {
        return $id !== null && preg_match('/^[A-Za-z0-9]{40}$/', $id) === 1;
    }

    protected function generateId(): string
    {
        return Str::random(40);
    }
}
