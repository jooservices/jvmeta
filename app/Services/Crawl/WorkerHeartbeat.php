<?php

declare(strict_types=1);

namespace App\Services\Crawl;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

final class WorkerHeartbeat
{
    public const CACHE_KEY = 'jvmeta:worker:heartbeat';

    public function beat(): void
    {
        Cache::put(self::CACHE_KEY, now()->getTimestamp(), now()->addDay());
    }

    public function lastBeatAt(): ?Carbon
    {
        $value = Cache::get(self::CACHE_KEY);
        if (! is_numeric($value)) {
            return null;
        }

        return Carbon::createFromTimestamp((int) $value);
    }

    public function isStale(?int $staleSeconds = null): bool
    {
        $threshold = $staleSeconds ?? (int) config('jvmeta_alerts.watchdog.heartbeat_stale_seconds', 600);
        $last = $this->lastBeatAt();

        if ($last === null) {
            return true;
        }

        return $last->diffInSeconds(now()) > $threshold;
    }
}
