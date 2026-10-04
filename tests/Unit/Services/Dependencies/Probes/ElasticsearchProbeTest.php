<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Dependencies\Probes;

use App\Services\Dependencies\Probes\ElasticsearchProbe;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class ElasticsearchProbeTest extends TestCase
{
    public function test_it_checks_cluster_health_with_a_short_timeout(): void
    {
        Http::preventStrayRequests();
        $host = rtrim(fake()->url(), '/');
        config()->set('elasticsearch.host', $host);
        config()->set('elasticsearch.verify_ssl', true);
        Http::fake([$host . '/_cluster/health' => Http::response(['status' => 'green'])]);

        self::assertTrue((new ElasticsearchProbe())->probe());
        Http::assertSent(static fn(Request $request): bool => $request->url() === $host . '/_cluster/health');
    }

    public function test_it_reports_a_non_successful_cluster_health_response_as_down(): void
    {
        Http::preventStrayRequests();
        $host = rtrim(fake()->url(), '/');
        config()->set('elasticsearch.host', $host);
        Http::fake([$host . '/_cluster/health' => Http::response([], 503)]);

        self::assertFalse((new ElasticsearchProbe())->probe());
    }
}
