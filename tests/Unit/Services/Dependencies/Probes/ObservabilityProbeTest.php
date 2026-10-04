<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Dependencies\Probes;

use App\Services\Dependencies\Probes\ObservabilityProbe;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class ObservabilityProbeTest extends TestCase
{
    public function test_it_checks_openobserve_health_when_enabled(): void
    {
        Http::preventStrayRequests();
        $url = rtrim(fake()->url(), '/');
        config()->set('openobserve.enabled', true);
        config()->set('openobserve.url', $url);
        Http::fake([$url . '/healthz' => Http::response(['status' => 'ok'])]);

        self::assertTrue((new ObservabilityProbe())->probe());
        Http::assertSent(static fn(Request $request): bool => $request->url() === $url . '/healthz');
    }

    public function test_disabled_openobserve_is_treated_as_available(): void
    {
        Http::preventStrayRequests();
        config()->set('openobserve.enabled', false);

        self::assertTrue((new ObservabilityProbe())->probe());
        Http::assertNothingSent();
    }
}
