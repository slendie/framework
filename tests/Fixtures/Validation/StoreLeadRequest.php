<?php

declare(strict_types=1);

namespace Slendie\Tests\Fixtures\Validation;

use Slendie\Http\FormRequest;
use Slendie\Validation\Validator;

class StoreLeadRequest extends FormRequest
{
    /** @var list<string> */
    public static array $log = [];

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:50',
            'email' => 'required|email',
            'slug' => 'nullable|alpha_dash',
        ];
    }

    public function messages(): array
    {
        return ['name.required' => 'Diz-nos o teu nome.'];
    }

    public function attributes(): array
    {
        return ['email' => 'endereço de e-mail'];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            self::$log[] = 'after';
        }];
    }

    protected function passedValidation(): void
    {
        self::$log[] = 'passed';
    }
}
