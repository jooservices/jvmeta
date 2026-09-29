<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

final class CreateApiKeyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'label' => ['required', 'string', 'max:255'],
            'abuse_rpm' => ['sometimes', 'integer', 'min:1', 'max:10000'],
        ];
    }
}
