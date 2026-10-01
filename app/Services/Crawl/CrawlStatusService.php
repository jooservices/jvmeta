<?php

declare(strict_types=1);

namespace App\Services\Crawl;

use App\Models\CrawlQueue;
use App\Models\CrawlRun;
use App\Models\Movie;
use App\Models\Performer;
use App\Models\Source;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;

/**
 * Aggregate crawl pipeline status for monitoring (MCP `crawl_status`).
 */
final class CrawlStatusService
{
    public function __construct(private readonly WorkerHeartbeat $heartbeat) {}

    /**
     * @return array<string, mixed>
     */
    public function status(): array
    {
        $staleSeconds = (int) config('jvmeta_sources.defaults.queue.stale_claim_timeout_seconds', 900);
        $now = now();
        $claimCutoff = now()->subSeconds($staleSeconds);
        $dayCutoff = now()->subDay();

        $queueByStatus = CrawlQueue::query()
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->map(static fn(mixed $value): int => (int) $value)
            ->all();

        $pendingKinds = DB::table('crawl_queue')
            ->select('kind', 'status', DB::raw('count(*) as total'))
            ->whereIn('status', [CrawlQueue::STATUS_PENDING, CrawlQueue::STATUS_CLAIMED, CrawlQueue::STATUS_FAILED])
            ->groupBy('kind', 'status')
            ->orderBy('kind')
            ->get()
            ->map(fn($row): array => [
                'kind' => $row->kind,
                'status' => $row->status,
                'count' => (int) $row->total,
            ])
            ->all();

        $oldestPending = CrawlQueue::query()
            ->where('status', CrawlQueue::STATUS_PENDING)
            ->orderBy('next_attempt_at')
            ->value('next_attempt_at');

        $stuckClaimed = CrawlQueue::query()
            ->where('status', CrawlQueue::STATUS_CLAIMED)
            ->where('claimed_at', '<', $claimCutoff)
            ->count();

        $sources = Source::query()
            ->orderBy('priority')
            ->orderBy('slug')
            ->get();

        $sourceRows = [];
        foreach ($sources as $source) {
            $sourceRows[] = [
                'slug' => $source->slug,
                'name' => $source->name,
                'enabled' => (bool) $source->enabled,
                'circuit_state' => (string) $source->circuit_state,
                'consecutive_failures' => (int) $source->consecutive_failures,
                'gap_seconds_current' => (float) $source->gap_seconds_current,
                'gap_seconds_min' => (float) $source->gap_seconds_min,
                'gap_seconds_max' => (float) $source->gap_seconds_max,
                'last_success_at' => $this->dateTime($source->getAttribute('last_success_at')),
                'last_error_at' => $this->dateTime($source->getAttribute('last_error_at')),
                'last_error' => $source->last_error,
            ];
        }

        $activeClaims = CrawlQueue::query()
            ->where('status', CrawlQueue::STATUS_CLAIMED)
            ->where('claimed_at', '>=', $claimCutoff)
            ->select('locked_by', DB::raw('count(*) as total'))
            ->groupBy('locked_by')
            ->pluck('total', 'locked_by')
            ->map(static fn(mixed $value): int => (int) $value)
            ->all();

        $workers = [];
        foreach ($this->heartbeat->instances() as $row) {
            $instance = $row['instance'];
            $workers[] = [
                'instance' => $instance,
                'last_heartbeat_at' => $row['last_heartbeat_at'],
                'stale' => $row['stale'],
                'active_claims' => $activeClaims[$instance] ?? 0,
            ];
        }

        $runs = CrawlRun::query()
            ->where('started_at', '>=', $dayCutoff)
            ->get();

        $runs24h = [
            'runs' => $runs->count(),
            'pages_fetched' => (int) $runs->sum('pages_fetched'),
            'movies_new' => (int) $runs->sum('movies_new'),
            'movies_updated' => (int) $runs->sum('movies_updated'),
            'failures' => (int) $runs->sum('failures'),
        ];

        return [
            'checked_at' => $now->toIso8601String(),
            'counts' => [
                'movies' => (int) Movie::query()->count(),
                'performers' => (int) Performer::query()->count(),
            ],
            'ingest' => [
                'movies_1h' => $this->newSince(Movie::class, 'first_seen_at', 3600),
                'movies_24h' => $this->newSince(Movie::class, 'first_seen_at', 86400),
                'performers_1h' => $this->newSince(Performer::class, 'first_seen_at', 3600),
                'performers_24h' => $this->newSince(Performer::class, 'first_seen_at', 86400),
            ],
            'queue' => [
                'by_status' => $queueByStatus,
                'by_kind_status' => $pendingKinds,
                'oldest_pending_at' => $oldestPending instanceof DateTimeInterface
                    ? $oldestPending->format(DATE_ATOM)
                    : null,
                'stuck_claimed' => $stuckClaimed,
            ],
            'sources' => $sourceRows,
            'workers' => $workers,
            'runs_24h' => $runs24h,
        ];
    }

    private function newSince(string $model, string $column, int $seconds): int
    {
        return (int) $model::query()->where($column, '>=', now()->subSeconds($seconds))->count();
    }

    private function dateTime(mixed $value): ?string
    {
        return $value instanceof DateTimeInterface ? $value->format(DATE_ATOM) : null;
    }
}
