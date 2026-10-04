<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Dependencies\Probes;

use App\Services\Dependencies\Probes\EmbedderProbe;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class EmbedderProbeTest extends TestCase
{
    public function test_it_checks_the_embedder_health_endpoint(): void
    {
        Http::preventStrayRequests();
        $url = rtrim(fake()->url(), '/');
        config()->set('elasticsearch.embedder_url', $url);
        Http::fake([$url . '/healthz' => Http::response(['status' => 'ok'])]);

        self::assertTrue((new EmbedderProbe())->probe());
        Http::assertSent(static fn(Request $request): bool => $request->url() === $url . '/healthz');
    }

    public function test_it_reports_a_failed_embedder_health_response_as_down(): void
    {
        Http::preventStrayRequests();
        $url = rtrim(fake()->url(), '/');
        config()->set('elasticsearch.embedder_url', $url);
        Http::fake([$url . '/healthz' => Http::response([], 503)]);

        self::assertFalse((new EmbedderProbe())->probe());
    }
}
