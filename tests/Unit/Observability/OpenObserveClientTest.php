<?php

declare(strict_types=1);

namespace Tests\Unit\Observability;

use App\Observability\OpenObserveClient;
use App\Observability\TelemetrySanitizer;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class OpenObserveClientTest extends TestCase
{
    public function test_disabled_client_does_not_http(): void
    {
        config()->set('openobserve.enabled', false);
        Http::fake();

        $client = new OpenObserveClient(new TelemetrySanitizer());
        $client->ingestLogs([['message' => 'hello', 'level' => 'info']]);
        $client->ingestMetrics([['name' => 'm', 'value' => 1]]);
        $client->ingestSpan([
            'name' => 'span',
            'trace_id' => str_repeat('a', 32),
            'span_id' => str_repeat('b', 16),
            'start_ns' => 1,
            'end_ns' => 2,
        ]);

        Http::assertNothingSent();
    }

    public function test_fail_open_on_http_error(): void
    {
        config()->set([
            'openobserve.enabled' => true,
            'openobserve.url' => 'http://openobserve.test',
            'openobserve.org' => 'default',
            'openobserve.stream_logs' => 'jvmeta_logs',
            'openobserve.email' => 'root@jvmeta.local',
            'openobserve.password' => 'secret',
            'openobserve.timeout' => 1,
        ]);
        Http::fake([
            'openobserve.test/*' => Http::response(['error' => 'no'], 500),
        ]);

        $client = new OpenObserveClient(new TelemetrySanitizer());
        $client->ingestLogs([['message' => 'x', 'level' => 'info']]);

        Http::assertSentCount(1);
        $this->assertTrue(true);
    }
}
