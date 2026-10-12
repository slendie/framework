<?php

declare(strict_types=1);

namespace Slendie\Validation;

use BackedEnum;
use Closure;
use Slendie\Validation\Rules\ConditionalRules;
use Slendie\Validation\Rules\Enum;
use Slendie\Validation\Rules\Exists;
use Slendie\Validation\Rules\File;
use Slendie\Validation\Rules\In;
use Slendie\Validation\Rules\NotIn;
use Slendie\Validation\Rules\Password;
use Slendie\Validation\Rules\ProhibitedIf;
use Slendie\Validation\Rules\RequiredIf;
use Slendie\Validation\Rules\Unique;

/**
 * Fábricas fluentes de regras: `Rule::unique('users')->ignore($id)`, `Rule::in([...])`, `Rule::enum(Status::class)`.
 */
final class Rule
{
    /**
     * @param  array<array-key, mixed>|BackedEnum|string  ...$values
     */
    public static function in(array|string|BackedEnum ...$values): In
    {
        return new In(self::flatten($values));
    }

    /**
     * @param  array<array-key, mixed>|BackedEnum|string  ...$values
     */
    public static function notIn(array|string|BackedEnum ...$values): NotIn
    {
        return new NotIn(self::flatten($values));
    }

    public static function unique(string $table, string $column = 'NULL'): Unique
    {
        return new Unique($table, $column);
    }

    public static function exists(string $table, string $column = 'NULL'): Exists
    {
        return new Exists($table, $column);
    }

    /**
     * @param  class-string  $type
     */
    public static function enum(string $type): Enum
    {
        return new Enum($type);
    }

    public static function requiredIf(bool|Closure $condition): RequiredIf
    {
        return new RequiredIf($condition);
    }

    public static function prohibitedIf(bool|Closure $condition): ProhibitedIf
    {
        return new ProhibitedIf($condition);
    }

    public static function password(int $min = 8): Password
    {
        return Password::min($min);
    }

    public static function file(): File
    {
        return new File();
    }

    /**
     * Aplica `$rules` quando a condição é verdadeira (`bool` ou `fn (array $data): bool`), senão `$default`.
     *
     * @param  string|list<mixed>  $rules
     * @param  string|list<mixed>  $default
     */
    public static function when(bool|Closure $condition, string|array $rules, string|array $default = []): ConditionalRules
    {
        return new ConditionalRules($condition, $rules, $default);
    }

    /**
     * @param  array<array-key, mixed>  $values
     * @return list<string>
     */
    private static function flatten(array $values): array
    {
        $flat = [];

        array_walk_recursive($values, static function (mixed $value) use (&$flat): void {
            $flat[] = $value instanceof BackedEnum ? (string) $value->value : (is_scalar($value) ? (string) $value : '');
        });

        return $flat;
    }
}
