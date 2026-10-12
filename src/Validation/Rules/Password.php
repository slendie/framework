<?php

declare(strict_types=1);

namespace Slendie\Validation\Rules;

use Closure;
use Slendie\Validation\ValidationRule;
use Slendie\Validation\Validator;
use Slendie\Validation\ValidatorAwareRule;

/**
 * `Password::min(10)->letters()->mixedCase()->numbers()->symbols()` (sem verificação de passwords comprometidas).
 */
class Password implements ValidationRule, ValidatorAwareRule
{
    /** @var (Closure(): (self|ValidationRule|string|list<mixed>))|null */
    protected static ?Closure $defaultCallback = null;

    protected ?Validator $validator = null;

    protected ?int $max = null;

    protected bool $letters = false;

    protected bool $mixedCase = false;

    protected bool $numbers = false;

    protected bool $symbols = false;

    final public function __construct(protected int $min = 8)
    {
    }

    public static function min(int $size): static
    {
        return new static($size);
    }

    /**
     * Define (ou limpa, com null) as regras por omissão devolvidas por `Password::default()`.
     *
     * @param  (Closure(): (self|ValidationRule|string|list<mixed>))|null  $callback
     */
    public static function defaults(?Closure $callback): void
    {
        static::$defaultCallback = $callback;
    }

    /** @return self|ValidationRule|string|list<mixed> */
    public static function default(): self|ValidationRule|string|array
    {
        return static::$defaultCallback !== null ? (static::$defaultCallback)() : static::min(8);
    }

    public function max(int $size): static
    {
        $this->max = $size;

        return $this;
    }

    public function letters(): static
    {
        $this->letters = true;

        return $this;
    }

    public function mixedCase(): static
    {
        $this->mixedCase = true;

        return $this;
    }

    public function numbers(): static
    {
        $this->numbers = true;

        return $this;
    }

    public function symbols(): static
    {
        $this->symbols = true;

        return $this;
    }

    public function setValidator(Validator $validator): static
    {
        $this->validator = $validator;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail($this->message($attribute, 'string'));

            return;
        }

        if (mb_strlen($value) < $this->min) {
            $fail($this->message($attribute, 'password.min', ['min' => $this->min]));
        }

        if ($this->max !== null && mb_strlen($value) > $this->max) {
            $fail($this->message($attribute, 'password.max', ['max' => $this->max]));
        }

        if ($this->letters && preg_match('/\pL/u', $value) !== 1) {
            $fail($this->message($attribute, 'password.letters'));
        }

        if ($this->mixedCase && preg_match('/(\p{Ll}+.*\p{Lu})|(\p{Lu}+.*\p{Ll})/u', $value) !== 1) {
            $fail($this->message($attribute, 'password.mixed'));
        }

        if ($this->numbers && preg_match('/\pN/u', $value) !== 1) {
            $fail($this->message($attribute, 'password.numbers'));
        }

        if ($this->symbols && preg_match('/\p{Z}|\p{S}|\p{P}/u', $value) !== 1) {
            $fail($this->message($attribute, 'password.symbols'));
        }
    }

    /**
     * @param  array<string, mixed>  $replace
     */
    protected function message(string $attribute, string $key, array $replace = []): string
    {
        return $this->validator?->failMessage($attribute, $key, [], $replace) ?? 'The :attribute is invalid.';
    }
}
