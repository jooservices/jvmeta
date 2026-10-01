<?php

declare(strict_types=1);

namespace App\Services\Health;

use App\Models\CrawlQueue;
use App\Models\Source;
use App\Services\Crawl\WorkerHeartbeat;
use Illuminate\Support\Facades\DB;
use Throwable;

final class StatusHealthCheck
{
    public function __construct(private readonly WorkerHeartbeat $heartbeat) {}

    /**
     * @return array{
     *   status: string,
     *   checked_at: string,
     *   database: string,
     *   worker: array{last_heartbeat_at: string|null, stale: bool},
     *   instances: list<array{instance: string, last_heartbeat_at: string|null, stale: bool}>,
     *   queue: array{pending: int, claimed: int, failed: int},
     *   sources: list<array{slug: string, circuit_state: string, last_success_at: string|null, last_error_at: string|null, consecutive_failures: int}>
     * }
     */
    public function check(): array
    {
        $database = $this->databaseStatus();
        $sources = Source::query()
            ->orderBy('priority')
            ->orderBy('slug')
            ->get(['slug', 'circuit_state', 'last_success_at', 'last_error_at', 'consecutive_failures']);

        $queue = [
            'pending' => CrawlQueue::query()->where('status', CrawlQueue::STATUS_PENDING)->count(),
            'claimed' => CrawlQueue::query()->where('status', CrawlQueue::STATUS_CLAIMED)->count(),
            'failed' => CrawlQueue::query()->where('status', CrawlQueue::STATUS_FAILED)->count(),
        ];

        $lastHeartbeat = $this->heartbeat->lastBeatAt();
        $staleSeconds = (int) config('jvmeta_alerts.watchdog.heartbeat_stale_seconds', 600);
        $workerStale = $lastHeartbeat === null
            || $lastHeartbeat->diffInSeconds(now()) > $staleSeconds;

        $sourceRows = [];
        foreach ($sources as $source) {
            $lastSuccess = $source->getAttribute('last_success_at');
            $lastError = $source->getAttribute('last_error_at');

            $sourceRows[] = [
                'slug' => $source->slug,
                'circuit_state' => (string) $source->circuit_state,
                'last_success_at' => $lastSuccess instanceof \DateTimeInterface
                    ? $lastSuccess->format(DATE_ATOM)
                    : null,
                'last_error_at' => $lastError instanceof \DateTimeInterface
                    ? $lastError->format(DATE_ATOM)
                    : null,
                'consecutive_failures' => (int) $source->consecutive_failures,
            ];
        }

        $anyOpen = false;
        foreach ($sources as $source) {
            if ($source->circuit_state === Source::CIRCUIT_OPEN) {
                $anyOpen = true;
                break;
            }
        }

        $status = 'ok';
        if ($database !== 'ok') {
            $status = 'down';
        } elseif ($anyOpen || $workerStale) {
            $status = 'degraded';
        }

        return [
            'status' => $status,
            'checked_at' => now()->toIso8601String(),
            'database' => $database,
            'worker' => [
                'last_heartbeat_at' => $lastHeartbeat?->toIso8601String(),
                'stale' => $workerStale,
            ],
            'instances' => $this->heartbeat->instances($staleSeconds),
            'queue' => $queue,
            'sources' => $sourceRows,
        ];
    }

    private function databaseStatus(): string
    {
        try {
            DB::connection()->getPdo();

            return 'ok';
        } catch (Throwable) {
            return 'down';
        }
    }
}
