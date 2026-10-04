<?php

declare(strict_types=1);

namespace App\Console\Commands\Crawler;

use App\Events\CrawlSourceUnhealthy;
use App\Events\WorkerHeartbeatStale;
use App\Models\Source;
use App\Services\Crawl\WorkerHeartbeat;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Event;

final class WatchdogCommand extends Command
{
    protected $signature = 'crawler:watchdog';

    protected $aliases = ['jvmeta:watchdog'];

    protected $description = 'Check worker heartbeat and long-stale sources; alert via Telegram + activity log.';

    public function handle(WorkerHeartbeat $heartbeat): int
    {
        if ($heartbeat->isStale()) {
            Event::dispatch(new WorkerHeartbeatStale([
                'last_heartbeat_at' => $heartbeat->lastBeatAt()?->toIso8601String(),
                'stale_seconds' => (int) config('jvmeta_alerts.watchdog.heartbeat_stale_seconds', 600),
            ]));
            $this->warn('Worker heartbeat stale.');
        } else {
            $this->info('Worker heartbeat ok.');
        }

        $staleSeconds = (int) config('jvmeta_alerts.watchdog.source_stale_seconds', 86400);
        $cutoff = now()->subSeconds($staleSeconds);

        $staleSources = Source::query()
            ->where('enabled', true)
            ->where(function ($query) use ($cutoff): void {
                $query->whereNull('last_success_at')
                    ->orWhere('last_success_at', '<', $cutoff);
            })
            ->where('circuit_state', '!=', Source::CIRCUIT_OPEN)
            ->pluck('slug');

        foreach ($staleSources as $slug) {
            Event::dispatch(new CrawlSourceUnhealthy((string) $slug, 'source_stale', [
                'stale_seconds' => $staleSeconds,
            ]));
            $this->warn("Source stale: {$slug}");
        }

        return self::SUCCESS;
    }
}
