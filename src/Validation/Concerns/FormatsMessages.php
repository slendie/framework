<?php

declare(strict_types=1);

namespace Slendie\Validation\Concerns;

use BackedEnum;
use DateTimeInterface;
use Slendie\Http\UploadedFile;
use Slendie\Support\Str;
use Stringable;

/**
 * Mensagens de erro: personalizadas > `validation.custom` > tradução da regra, com `:placeholders`.
 */
trait FormatsMessages
{
    /** @var list<string> regras cuja mensagem depende do tipo (numeric/string/array/file) */
    protected array $sizeRules = ['min', 'max', 'size', 'between', 'gt', 'gte', 'lt', 'lte'];

    /**
     * Mensagem final para uma falha. `$rule` pode ser `unique`, `RequiredIf` ou uma chave como `password.min`.
     *
     * @param  list<string>  $parameters
     * @param  array<string, mixed>  $replace  substituições adicionais
     */
    public function failMessage(string $attribute, string $rule, array $parameters = [], array $replace = []): string
    {
        $rule = Str::snake($rule);
        $value = $this->getValue($attribute);

        $line = $this->customMessageFor($attribute, $rule, $value)
            ?? $this->extensionMessages[$rule]
            ?? $this->translatedMessageFor($attribute, $rule, $value);

        $replacements = [
            'attribute' => $this->displayableAttribute($attribute),
            'input' => $this->displayableValue($attribute, $value),
            ...$this->parameterReplacements($rule, $parameters, $attribute),
            ...$replace,
        ];

        return $this->translator->makeReplacements($line, $replacements);
    }

    /**
     * Traduz uma chave com os replacements dados (ex.: `validation.accepted`).
     *
     * @param  array<string, mixed>  $replace
     */
    public function trans(string $key, array $replace = []): string
    {
        return $this->translator->get($key, $replace);
    }

    /** Nome legível do campo: nomes personalizados > `validation.attributes` > snake_case com espaços. */
    public function displayableAttribute(string $attribute): string
    {
        $pattern = $this->implicitAttributes[$attribute] ?? null;

        foreach ($pattern === null ? [$attribute] : [$attribute, $pattern] as $key) {
            if (isset($this->customAttributes[$key])) {
                return $this->customAttributes[$key];
            }

            $attributes = $this->translator->raw('validation.attributes');
            $translated = is_array($attributes) ? ($attributes[$key] ?? null) : null;

            if (is_string($translated)) {
                return $translated;
            }
        }

        return str_replace('_', ' ', Str::snake($attribute));
    }

    /** Valor legível para as mensagens (`validation.values` > texto). */
    public function displayableValue(string $attribute, mixed $value): string
    {
        $values = $this->translator->raw('validation.values');
        $key = is_scalar($value) ? (string) $value : null;

        if ($key !== null && is_array($values) && is_array($values[$attribute] ?? null) && is_string($values[$attribute][$key] ?? null)) {
            return $values[$attribute][$key];
        }

        return match (true) {
            is_bool($value) => $value ? 'true' : 'false',
            $value === null => 'empty',
            $value instanceof BackedEnum => (string) $value->value,
            $value instanceof DateTimeInterface => $value->format('Y-m-d H:i:s'),
            $value instanceof UploadedFile => $value->clientName(),
            is_scalar($value), $value instanceof Stringable => (string) $value,
            default => '',
        };
    }

    /** Aplica `:attribute` e `:input` a uma mensagem vinda de uma regra-objecto (`$fail('...')`). */
    protected function replaceInMessage(string $message, string $attribute, mixed $value): string
    {
        return $this->translator->makeReplacements($message, [
            'attribute' => $this->displayableAttribute($attribute),
            'input' => $this->displayableValue($attribute, $value),
        ]);
    }

    protected function customMessageFor(string $attribute, string $rule, mixed $value): ?string
    {
        $pattern = $this->implicitAttributes[$attribute] ?? null;

        $keys = [$attribute.'.'.$rule];

        if ($pattern !== null) {
            $keys[] = $pattern.'.'.$rule;
        }

        $keys[] = $rule;

        foreach ($keys as $key) {
            $message = $this->customMessages[$key] ?? null;

            if (is_array($message)) {
                $message = $message[$this->typeOf($attribute, $value)] ?? null;
            }

            if (is_string($message)) {
                return $message;
            }
        }

        // validation.custom.{campo}.{regra}
        $custom = $this->translator->raw('validation.custom');

        if (is_array($custom)) {
            foreach ($pattern === null ? [$attribute] : [$attribute, $pattern] as $field) {
                $line = is_array($custom[$field] ?? null) ? ($custom[$field][$rule] ?? null) : null;

                if (is_string($line)) {
                    return $line;
                }
            }
        }

        return null;
    }

    protected function translatedMessageFor(string $attribute, string $rule, mixed $value): string
    {
        if (in_array($rule, $this->sizeRules, true)) {
            $line = $this->translator->raw('validation.'.$rule.'.'.$this->typeOf($attribute, $value));
        } else {
            $line = $this->translator->raw('validation.'.$rule);
        }

        return is_string($line) ? $line : 'The :attribute field is invalid.';
    }

    /** numeric | array | file | string — o tipo que decide a variante da mensagem de min/max/size... */
    protected function typeOf(string $attribute, mixed $value): string
    {
        if ($this->hasRule($attribute, ['Numeric', 'Integer', 'Decimal'])) {
            return 'numeric';
        }

        if (is_array($value) || $this->hasRule($attribute, ['Array'])) {
            return 'array';
        }

        if ($value instanceof UploadedFile) {
            return 'file';
        }

        return 'string';
    }

    /**
     * @param  list<string>  $p
     * @return array<string, string>
     */
    protected function parameterReplacements(string $rule, array $p, string $attribute): array
    {
        $first = $p[0] ?? '';

        return match ($rule) {
            'min' => ['min' => $first],
            'max' => ['max' => $first],
            'size' => ['size' => $first],
            'digits' => ['digits' => $first],
            'between', 'digits_between' => ['min' => $first, 'max' => $p[1] ?? ''],
            'decimal' => ['decimal' => isset($p[1]) ? $first.'-'.$p[1] : $first],
            'multiple_of' => ['value' => $first],
            'date_format' => ['format' => $first],
            'gt', 'gte', 'lt', 'lte' => ['value' => $this->hasAttribute($first)
                ? (string) $this->sizeOf($attribute, $this->getValue($first))
                : $first],
            'same', 'different' => ['other' => $this->displayableAttribute($first)],
            'confirmed' => ['other' => $this->displayableAttribute($attribute.'_confirmation')],
            'in_array' => ['other' => $first],
            'before', 'before_or_equal', 'after', 'after_or_equal' => ['date' => $this->hasAttribute($first) ? $this->displayableAttribute($first) : $first],
            'required_if', 'prohibited_if', 'accepted_if', 'declined_if', 'missing_if' => [
                'other' => $this->displayableAttribute($first),
                'value' => $this->displayableValue($first, $p[1] ?? null),
            ],
            'required_unless', 'prohibited_unless', 'missing_unless' => [
                'other' => $this->displayableAttribute($first),
                'values' => implode(', ', array_map(fn (string $v): string => $this->displayableValue($first, $v), array_slice($p, 1))),
            ],
            'required_with', 'required_with_all', 'required_without', 'required_without_all', 'missing_with', 'missing_with_all' => [
                'values' => implode(' / ', array_map(fn (string $name): string => $this->displayableAttribute($name), $p)),
            ],
            'in', 'not_in', 'starts_with', 'ends_with', 'doesnt_start_with', 'doesnt_end_with', 'contains', 'mimes', 'mimetypes', 'extensions' => [
                'values' => implode(', ', $p),
            ],
            default => [],
        };
    }
}
