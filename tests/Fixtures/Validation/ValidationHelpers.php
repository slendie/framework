<?php

declare(strict_types=1);

namespace Slendie\Tests\Fixtures\Validation;

use Slendie\Http\UploadedFile;
use Slendie\Validation\Factory;
use Slendie\Validation\Translator;
use Slendie\Validation\Validator;

/**
 * Validators sobre os textos da framework, em pt ou en, sem Application.
 */
final class ValidationHelpers
{
    public static function translator(string $locale = 'pt', ?string $projectLang = null): Translator
    {
        return new Translator(dirname(__DIR__, 3).'/lang', $projectLang, $locale, 'en');
    }

    public static function factory(string $locale = 'pt'): Factory
    {
        return new Factory(self::translator($locale));
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @param  array<string, mixed>  $rules
     * @param  array<string, string>  $messages
     * @param  array<string, string>  $attributes
     */
    public static function make(array $data, array $rules, string $locale = 'pt', array $messages = [], array $attributes = []): Validator
    {
        return self::factory($locale)->make($data, $rules, $messages, $attributes);
    }

    public static function passes(mixed $value, string $rule): bool
    {
        return self::make(['f' => $value], ['f' => $rule])->passes();
    }

    /** Ficheiro temporário com o conteúdo dado, como UploadedFile de teste. */
    public static function upload(string $name, string $content, ?string $mime = null): UploadedFile
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'slv');
        file_put_contents($path, $content);

        return new UploadedFile($path, $name, $mime, strlen($content), UPLOAD_ERR_OK, true);
    }

    /** PNG válido de 1x1. */
    public static function png(): string
    {
        return (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==');
    }
}
