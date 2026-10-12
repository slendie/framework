<?php

declare(strict_types=1);

namespace Slendie\Tests\Fixtures\Validation;

use Slendie\Http\FormRequest;

final class DeniedRequest extends FormRequest
{
    public function authorize(): bool
    {
        return false;
    }

    public function rules(): array
    {
        return ['name' => 'required'];
    }
}
