<?php

declare(strict_types=1);

namespace Slendie\Support\Facades;

use Slendie\Support\Facade;

/**
 * @method static \Slendie\Validation\Validator make(array<array-key, mixed> $data, array<string, mixed> $rules, array<string, string> $messages = [], array<string, string> $attributes = [])
 * @method static array<string, mixed> validate(array<array-key, mixed> $data, array<string, mixed> $rules, array<string, string> $messages = [], array<string, string> $attributes = [])
 * @method static \Slendie\Validation\Factory extend(string $rule, \Closure $extension, ?string $message = null)
 * @method static \Slendie\Validation\Translator getTranslator()
 *
 * @see \Slendie\Validation\Factory
 */
class Validator extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'validator';
    }
}
