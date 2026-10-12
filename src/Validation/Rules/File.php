<?php

declare(strict_types=1);

namespace Slendie\Validation\Rules;

use Closure;
use Slendie\Validation\ValidationRule;
use Slendie\Validation\Validator;
use Slendie\Validation\ValidatorAwareRule;

/**
 * `Rule::file()->types(['jpg', 'png'])->max(2048)` — ficheiro com extensões/tamanho (KB) permitidos.
 */
class File implements ValidationRule, ValidatorAwareRule
{
    protected ?Validator $validator = null;

    /** @var list<string> */
    protected array $rules = ['file'];

    public function setValidator(Validator $validator): static
    {
        $this->validator = $validator;

        return $this;
    }

    public function image(): static
    {
        $this->rules[] = 'image';

        return $this;
    }

    /**
     * @param  string|list<string>  $extensions
     */
    public function types(string|array $extensions): static
    {
        $this->rules[] = 'mimes:'.implode(',', (array) $extensions);

        return $this;
    }

    public function min(int $kilobytes): static
    {
        $this->rules[] = 'min:'.$kilobytes;

        return $this;
    }

    public function max(int $kilobytes): static
    {
        $this->rules[] = 'max:'.$kilobytes;

        return $this;
    }

    public function size(int $kilobytes): static
    {
        $this->rules[] = 'size:'.$kilobytes;

        return $this;
    }

    public function between(int $min, int $max): static
    {
        $this->rules[] = 'between:'.$min.','.$max;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($this->validator === null) {
            throw new \LogicException('A regra File precisa de um Validator.');
        }

        foreach ($this->validator->nestedMessages($attribute, $value, $this->rules) as $message) {
            $fail($message);
        }
    }
}
