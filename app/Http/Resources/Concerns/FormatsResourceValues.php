<?php

declare(strict_types=1);

namespace App\Http\Resources\Concerns;

trait FormatsResourceValues
{
    private function dateString(mixed $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function dateTimeString(mixed $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format(DATE_ATOM);
        }

        return is_string($value) && $value !== '' ? $value : null;
    }

    /** @return array<string, mixed> */
    private function attrsArray(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }
}
