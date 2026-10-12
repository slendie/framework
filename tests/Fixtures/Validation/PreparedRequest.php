<?php

declare(strict_types=1);

namespace Slendie\Tests\Fixtures\Validation;

use Slendie\Http\FormRequest;

final class PreparedRequest extends FormRequest
{
    public function rules(): array
    {
        return ['name' => 'required|min:3', 'slug' => 'required'];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['slug' => strtolower(str_replace(' ', '-', (string) $this->input('name')))]);
    }
}
