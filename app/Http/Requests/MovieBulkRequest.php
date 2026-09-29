<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Exceptions\BulkLimitExceededException;
use App\Exceptions\InvalidFilterException;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

final class MovieBulkRequest extends FormRequest
{
    public const BULK_LIMIT = 100;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'codes' => ['required', 'array'],
            'codes.*' => ['required', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->exceedsLimit()) {
                $validator->errors()->add('codes', 'Bulk lookup is limited to ' . self::BULK_LIMIT . ' codes.');
            }
        });
    }

    protected function failedValidation(Validator $validator): void
    {
        if ($this->exceedsLimit()) {
            throw new BulkLimitExceededException(self::BULK_LIMIT);
        }

        throw new InvalidFilterException('Invalid bulk lookup codes.', $validator->errors()->toArray());
    }

    /** @return list<string> */
    public function codes(): array
    {
        $codes = $this->validated('codes');

        if (! is_array($codes)) {
            return [];
        }

        return array_values(array_map(static fn(mixed $code): string => (string) $code, $codes));
    }

    private function exceedsLimit(): bool
    {
        $codes = $this->input('codes');

        return is_array($codes) && count($codes) > self::BULK_LIMIT;
    }
}
