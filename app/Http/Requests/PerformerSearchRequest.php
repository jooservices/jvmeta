<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Exceptions\InvalidFilterException;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

final class PerformerSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'q' => ['sometimes', 'string', 'min:2'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $q = $this->input('q');
        if (is_string($q)) {
            $this->merge(['q' => trim($q)]);
        }
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new InvalidFilterException(
            'Invalid performer search filter.',
            $validator->errors()->toArray(),
        );
    }
}
