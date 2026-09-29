<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Services\Auth\ApiKeyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

final class RateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_over_abuse_rpm_returns_rate_limited_problem_with_retry_after(): void
    {
        RateLimiter::clear('api-key:1');
        $created = app(ApiKeyService::class)->create(fake()->words(2, true), 1);

        $this->getJson('/api/v1/auth/verify', [
            'X-API-Key' => $created->plaintext,
        ])->assertOk();

        $response = $this->getJson('/api/v1/auth/verify', [
            'X-API-Key' => $created->plaintext,
        ]);

        $response->assertTooManyRequests()
            ->assertHeader('Retry-After')
            ->assertJsonPath('type', 'rate_limited');
        $this->assertDatabaseHas('api_usage_log', [
            'api_key_id' => $created->model->getKey(),
            'status_code' => 429,
        ]);
    }
}
