<?php

declare(strict_types=1);

namespace App\Services\Dependencies\Probes;

use App\Services\Dependencies\DependencyProbe;
use Illuminate\Support\Facades\Http;
use Throwable;

final class ObservabilityProbe implements DependencyProbe
{
    public function probe(): bool
    {
        if (! (bool) config('openobserve.enabled', false)) {
            return true;
        }

        $url = rtrim((string) config('openobserve.url', 'http://openobserve:5080'), '/');

        try {
            return Http::connectTimeout(1)->timeout(2)->get($url . '/healthz')->successful();
        } catch (Throwable) {
            return false;
        }
    }
}
