<?php

declare(strict_types=1);

namespace Slendie\Validation\Rules;

use BackedEnum;
use Closure;
use ReflectionEnum;
use Slendie\Validation\ValidationRule;
use Slendie\Validation\Validator;
use Slendie\Validation\ValidatorAwareRule;
use UnitEnum;

/**
 * `Rule::enum(Status::class)->only([Status::Open])` — o valor tem de ser um caso do enum.
 */
class Enum implements ValidationRule, ValidatorAwareRule
{
    protected ?Validator $validator = null;

    /** @var list<UnitEnum> */
    protected array $only = [];

    /** @var list<UnitEnum> */
    protected array $except = [];

    /**
     * @param  class-string  $type
     */
    public function __construct(protected string $type)
    {
    }

    public function setValidator(Validator $validator): static
    {
        $this->validator = $validator;

        return $this;
    }

    /**
     * @param  list<UnitEnum>  $values
     */
    public function only(array $values): static
    {
        $this->only = array_values($values);

        return $this;
    }

    /**
     * @param  list<UnitEnum>  $values
     */
    public function except(array $values): static
    {
        $this->except = array_values($values);

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $this->passes($value)) {
            $fail($this->validator?->failMessage($attribute, 'enum') ?? 'The selected :attribute is invalid.');
        }
    }

    protected function passes(mixed $value): bool
    {
        if ($value instanceof $this->type) {
            $case = $value;
        } elseif (! is_subclass_of($this->type, BackedEnum::class) || ! is_scalar($value)) {
            return false;
        } else {
            $case = $this->resolveCase($value);
        }

        if (! $case instanceof UnitEnum) {
            return false;
        }

        if ($this->only !== [] && ! in_array($case, $this->only, true)) {
            return false;
        }

        return ! in_array($case, $this->except, true);
    }

    protected function resolveCase(int|float|string|bool $value): ?UnitEnum
    {
        /** @var class-string<BackedEnum> $type */
        $type = $this->type;
        $backing = (new ReflectionEnum($type))->getBackingType()?->getName();

        if ($backing === 'int') {
            if (! is_numeric($value) || (string) (int) $value !== (string) $value) {
                return null;
            }

            return $type::tryFrom((int) $value);
        }

        return $type::tryFrom((string) $value);
    }
}
