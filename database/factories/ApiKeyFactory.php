<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ApiKey;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<ApiKey> */
class ApiKeyFactory extends Factory
{
    protected $model = ApiKey::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['prefix' => 'jvm_' . Str::lower(Str::random(8)), 'key_hash' => hash('sha256', fake()->unique()->uuid()), 'label' => fake()->words(2, true), 'status' => ApiKey::STATUS_ACTIVE, 'revoked_at' => null, 'abuse_rpm' => 60, 'created_at' => now()];
    }
}
