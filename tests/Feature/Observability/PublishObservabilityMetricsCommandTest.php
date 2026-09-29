<?php

declare(strict_types=1);

namespace Tests\Feature\Observability;

use App\Models\Source;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class PublishObservabilityMetricsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_publishes_gauge_points_when_enabled(): void
    {
        Source::factory()->create([
            'slug' => 'javdb',
            'circuit_state' => Source::CIRCUIT_CLOSED,
            'gap_seconds_current' => 30,
            'consecutive_failures' => 0,
            'last_success_at' => now()->subMinutes(5),
        ]);

        config()->set([
            'openobserve.enabled' => true,
            'openobserve.url' => 'http://openobserve.test',
            'openobserve.org' => 'default',
            'openobserve.email' => 'root@jvmeta.local',
            'openobserve.password' => 'secret',
            'openobserve.timeout' => 1,
        ]);
        Http::fake([
            'openobserve.test/*' => Http::response(['code' => 200], 200),
        ]);

        $this->artisan('jvmeta:obs-publish-metrics')->assertSuccessful();

        Http::assertSent(function ($request): bool {
            return str_contains($request->url(), '/v1/metrics')
                && str_contains($request->body(), 'jvmeta_source_circuit_state');
        });
    }

    public function test_command_skips_when_disabled(): void
    {
        config()->set('openobserve.enabled', false);
        Http::fake();

        $this->artisan('jvmeta:obs-publish-metrics')->assertSuccessful();
        Http::assertNothingSent();
    }
}
