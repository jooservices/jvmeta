<?php

declare(strict_types=1);

namespace Tests\Feature\Health;

use App\Models\Source;
use App\Services\Crawl\WorkerHeartbeat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

final class HealthControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_is_public_and_includes_source_status(): void
    {
        Source::query()->create([
            'slug' => 'javdb',
            'name' => 'JavDB',
            'base_url' => 'https://javdb.com',
            'enabled' => true,
            'priority' => 10,
            'needs_proxy' => false,
            'gap_seconds_default' => 20,
            'gap_seconds_min' => 20,
            'gap_seconds_max' => 60,
            'gap_seconds_current' => 20,
            'consecutive_failures' => 0,
            'circuit_state' => Source::CIRCUIT_CLOSED,
            'last_success_at' => now(),
        ]);

        app(WorkerHeartbeat::class)->beat();

        $response = $this->getJson('/api/health');

        $response->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('database', 'ok')
            ->assertJsonPath('sources.0.slug', 'javdb')
            ->assertJsonPath('worker.stale', false);

        $this->assertIsArray($response->json('instances'));

        $this->assertArrayNotHasKey('movies', $response->json());
        $this->assertArrayNotHasKey('titles', $response->json());
    }

    public function test_health_marks_degraded_when_worker_stale(): void
    {
        Cache::forget(WorkerHeartbeat::CACHE_KEY);

        $response = $this->getJson('/api/health');

        $response->assertOk()
            ->assertJsonPath('status', 'degraded')
            ->assertJsonPath('worker.stale', true);
    }
}
