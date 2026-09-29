<?php

declare(strict_types=1);

namespace App\Support\Auth;

use App\Models\ApiKey;

final readonly class CreatedApiKey
{
    public function __construct(
        public string $plaintext,
        public string $prefix,
        public ApiKey $model,
    ) {}
}
