<?php

declare(strict_types=1);

namespace App\Console\Commands\Observability;

use App\Models\ApiUsageLog;
use App\Models\CrawlEvent;
use App\Models\CrawlQueue;
use App\Models\CrawlRun;
use App\Models\Movie;
use App\Models\Performer;
use App\Models\ReviewFlag;
use App\Models\Source;
use App\Observability\OpenObserveClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class PublishMetricsCommand extends Command
{
    protected $signature = 'obs:publish-metrics';

    protected $aliases = ['jvmeta:obs-publish-metrics'];

    protected $description = 'Publish jvmeta ops and quality aggregate metrics to OpenObserve.';

    public function handle(OpenObserveClient $client): int
    {
        if (! $client->enabled()) {
            $this->components->info('OpenObserve disabled; skipping metrics publish.');

            return self::SUCCESS;
        }

        $points = array_merge(
            $this->sourceMetrics(),
            $this->queueMetrics(),
            $this->crawlEventMetrics(),
            $this->apiMetrics(),
            $this->qualityMetrics(),
            $this->crawlRunMetrics(),
            $this->catalogMetrics(),
            $this->failedJobMetrics(),
            $this->dependencyProbeMetrics(),
        );

        $client->ingestMetrics($points);
        $this->components->info('Published ' . count($points) . ' metric points.');

        return self::SUCCESS;
    }

    /**
     * @return list<array{name: string, value: float|int, labels?: array<string, string>}>
     */
    private function sourceMetrics(): array
    {
        $points = [];
        $circuitMap = [
            Source::CIRCUIT_CLOSED => 0,
            Source::CIRCUIT_HALF_OPEN => 1,
            Source::CIRCUIT_OPEN => 2,
        ];

        foreach (Source::query()->get() as $source) {
            $labels = ['source_slug' => $source->slug];
            $points[] = [
                'name' => 'jvmeta_source_circuit_state',
                'value' => $circuitMap[$source->circuit_state] ?? -1,
                'labels' => $labels,
            ];
            $points[] = [
                'name' => 'jvmeta_source_gap_seconds',
                'value' => (float) $source->gap_seconds_current,
                'labels' => $labels,
            ];
            $points[] = [
                'name' => 'jvmeta_source_consecutive_failures',
                'value' => (int) $source->consecutive_failures,
                'labels' => $labels,
            ];
            $age = $source->last_success_at !== null
                ? max(0, now()->diffInSeconds($source->last_success_at))
                : -1;
            $points[] = [
                'name' => 'jvmeta_source_last_success_age_seconds',
                'value' => $age,
                'labels' => $labels,
            ];
        }

        return $points;
    }

    /**
     * @return list<array{name: string, value: float|int, labels?: array<string, string>}>
     */
    private function queueMetrics(): array
    {
        $points = [];
        $rows = CrawlQueue::query()
            ->selectRaw('status, count(*) as c')
            ->groupBy('status')
            ->get();

        foreach ($rows as $row) {
            $points[] = [
                'name' => 'jvmeta_crawl_queue_depth',
                'value' => (int) $row->getAttribute('c'),
                'labels' => ['status' => (string) $row->status],
            ];
        }

        $oldestClaimed = CrawlQueue::query()
            ->where('status', CrawlQueue::STATUS_CLAIMED)
            ->whereNotNull('claimed_at')
            ->orderBy('claimed_at')
            ->value('claimed_at');
        $points[] = [
            'name' => 'jvmeta_crawl_queue_claimed_age_seconds',
            'value' => $oldestClaimed !== null ? max(0, (int) now()->diffInSeconds($oldestClaimed)) : -1,
        ];

        $avgAttempts = CrawlQueue::query()
            ->whereIn('status', [CrawlQueue::STATUS_PENDING, CrawlQueue::STATUS_FAILED])
            ->avg('attempts');
        $points[] = [
            'name' => 'jvmeta_crawl_queue_avg_attempts',
            'value' => $avgAttempts !== null ? (float) $avgAttempts : 0.0,
        ];

        return $points;
    }

    /**
     * @return list<array{name: string, value: float|int, labels?: array<string, string>}>
     */
    private function crawlEventMetrics(): array
    {
        $points = [];
        $since = now()->subMinutes(5);
        $rows = CrawlEvent::query()
            ->where('created_at', '>=', $since)
            ->selectRaw('kind, count(*) as c')
            ->groupBy('kind')
            ->get();

        foreach ($rows as $row) {
            $points[] = [
                'name' => 'jvmeta_crawl_events_5m',
                'value' => (int) $row->getAttribute('c'),
                'labels' => ['kind' => (string) $row->kind],
            ];
        }

        return $points;
    }

    /**
     * @return list<array{name: string, value: float|int, labels?: array<string, string>}>
     */
    private function apiMetrics(): array
    {
        $since = now()->subMinute();
        $rows = ApiUsageLog::query()
            ->where('created_at', '>=', $since)
            ->get(['endpoint', 'status_code', 'response_ms']);

        if ($rows->isEmpty()) {
            return [
                ['name' => 'jvmeta_api_requests_1m', 'value' => 0],
                ['name' => 'jvmeta_api_error_rate_1m', 'value' => 0],
                ['name' => 'jvmeta_api_p50_ms_1m', 'value' => 0],
                ['name' => 'jvmeta_api_p95_ms_1m', 'value' => 0],
                ['name' => 'jvmeta_api_429_1m', 'value' => 0],
            ];
        }

        $total = $rows->count();
        $errors = $rows->filter(fn($r) => (int) $r->status_code >= 400)->count();
        $rateLimited = $rows->filter(fn($r) => (int) $r->status_code === 429)->count();
        $latencies = $rows->pluck('response_ms')->filter(fn($ms) => $ms !== null)->map(fn($ms) => (int) $ms)->sort()->values();

        $points = [
            ['name' => 'jvmeta_api_requests_1m', 'value' => $total],
            ['name' => 'jvmeta_api_error_rate_1m', 'value' => $total > 0 ? $errors / $total : 0],
            ['name' => 'jvmeta_api_p50_ms_1m', 'value' => $this->percentile($latencies->all(), 0.50)],
            ['name' => 'jvmeta_api_p95_ms_1m', 'value' => $this->percentile($latencies->all(), 0.95)],
            ['name' => 'jvmeta_api_429_1m', 'value' => $rateLimited],
        ];

        foreach ($rows->groupBy('endpoint') as $endpoint => $group) {
            $points[] = [
                'name' => 'jvmeta_api_requests_by_endpoint_1m',
                'value' => $group->count(),
                'labels' => ['endpoint' => (string) $endpoint],
            ];
            $epLat = $group->pluck('response_ms')->filter(fn($ms) => $ms !== null)->map(fn($ms) => (int) $ms)->sort()->values()->all();
            $points[] = [
                'name' => 'jvmeta_api_p95_ms_by_endpoint_1m',
                'value' => $this->percentile($epLat, 0.95),
                'labels' => ['endpoint' => (string) $endpoint],
            ];
        }

        return $points;
    }

    /**
     * @return list<array{name: string, value: float|int, labels?: array<string, string>}>
     */
    private function qualityMetrics(): array
    {
        $points = [];

        $points[] = [
            'name' => 'jvmeta_movies_needs_review',
            'value' => Movie::query()->where('needs_review', true)->count(),
        ];
        $points[] = [
            'name' => 'jvmeta_review_flags_open',
            'value' => ReviewFlag::query()->whereNull('resolved_at')->count(),
        ];

        $tiers = Movie::query()
            ->selectRaw('completeness_tier, count(*) as c')
            ->groupBy('completeness_tier')
            ->get();
        foreach ($tiers as $tier) {
            $points[] = [
                'name' => 'jvmeta_movies_completeness_tier',
                'value' => (int) $tier->getAttribute('c'),
                'labels' => ['tier' => (string) ($tier->completeness_tier ?? 'unknown')],
            ];
        }

        // Conflict rate per field: movies with >=2 distinct value_hash / movies with observations for field.
        $driver = DB::connection()->getDriverName();
        if ($driver === 'pgsql') {
            $conflicts = DB::select(<<<'SQL'
                SELECT field,
                       COUNT(*) FILTER (WHERE distinct_hashes >= 2)::float
                         / NULLIF(COUNT(*), 0) AS conflict_rate
                FROM (
                    SELECT movie_id, field, COUNT(DISTINCT value_hash) AS distinct_hashes
                    FROM movie_observations
                    GROUP BY movie_id, field
                ) per_movie
                GROUP BY field
            SQL);
        } else {
            $conflicts = DB::select(<<<'SQL'
                SELECT field,
                       CAST(SUM(CASE WHEN distinct_hashes >= 2 THEN 1 ELSE 0 END) AS REAL)
                         / NULLIF(COUNT(*), 0) AS conflict_rate
                FROM (
                    SELECT movie_id, field, COUNT(DISTINCT value_hash) AS distinct_hashes
                    FROM movie_observations
                    GROUP BY movie_id, field
                ) per_movie
                GROUP BY field
            SQL);
        }

        foreach ($conflicts as $row) {
            $points[] = [
                'name' => 'jvmeta_observation_conflict_rate',
                'value' => (float) ($row->conflict_rate ?? 0),
                'labels' => ['field' => (string) $row->field],
            ];
        }

        return $points;
    }

    /**
     * @return list<array{name: string, value: float|int, labels?: array<string, string>}>
     */
    private function crawlRunMetrics(): array
    {
        $points = [];
        $since = now()->subHour();
        $rows = CrawlRun::query()
            ->where('started_at', '>=', $since)
            ->selectRaw('source_slug,
                COALESCE(SUM(pages_fetched),0) as pages_fetched,
                COALESCE(SUM(movies_new),0) as movies_new,
                COALESCE(SUM(movies_updated),0) as movies_updated,
                COALESCE(SUM(failures),0) as failures,
                COALESCE(SUM(proxy_requests),0) as proxy_requests,
                COALESCE(SUM(proxy_bytes),0) as proxy_bytes,
                COALESCE(SUM(browser_fetches),0) as browser_fetches')
            ->groupBy('source_slug')
            ->get();

        foreach ($rows as $row) {
            $labels = ['source_slug' => (string) $row->source_slug];
            foreach (['pages_fetched', 'movies_new', 'movies_updated', 'failures', 'proxy_requests', 'proxy_bytes', 'browser_fetches'] as $metric) {
                $points[] = [
                    'name' => 'jvmeta_crawl_run_' . $metric . '_1h',
                    'value' => (int) $row->{$metric},
                    'labels' => $labels,
                ];
            }
        }

        return $points;
    }

    /**
     * @return list<array{name: string, value: float|int, labels?: array<string, string>}>
     */
    private function catalogMetrics(): array
    {
        return [
            ['name' => 'jvmeta_movies_total', 'value' => Movie::query()->count()],
            ['name' => 'jvmeta_performers_total', 'value' => Performer::query()->count()],
            [
                'name' => 'jvmeta_observations_1d',
                'value' => DB::table('movie_observations')->where('crawled_at', '>=', now()->subDay())->count(),
            ],
        ];
    }

    /**
     * @return list<array{name: string, value: float|int, labels?: array<string, string>}>
     */
    private function failedJobMetrics(): array
    {
        if (! Schema::hasTable('failed_jobs')) {
            return [['name' => 'jvmeta_failed_jobs', 'value' => 0]];
        }

        return [
            ['name' => 'jvmeta_failed_jobs', 'value' => DB::table('failed_jobs')->count()],
            [
                'name' => 'jvmeta_failed_jobs_1h',
                'value' => DB::table('failed_jobs')->where('failed_at', '>=', now()->subHour())->count(),
            ],
        ];
    }

    /**
     * @return list<array{name: string, value: float|int, labels?: array<string, string>}>
     */
    private function dependencyProbeMetrics(): array
    {
        $points = [];

        try {
            DB::connection()->getPdo();
            $points[] = ['name' => 'jvmeta_dep_up', 'value' => 1, 'labels' => ['dependency' => 'postgres']];
        } catch (Throwable) {
            $points[] = ['name' => 'jvmeta_dep_up', 'value' => 0, 'labels' => ['dependency' => 'postgres']];
        }

        $esHost = rtrim((string) config('elasticsearch.host'), '/');
        try {
            $ok = Http::timeout(2)->get("{$esHost}/_cluster/health")->successful();
            $points[] = ['name' => 'jvmeta_dep_up', 'value' => $ok ? 1 : 0, 'labels' => ['dependency' => 'elasticsearch']];
        } catch (Throwable) {
            $points[] = ['name' => 'jvmeta_dep_up', 'value' => 0, 'labels' => ['dependency' => 'elasticsearch']];
        }

        $ooUrl = rtrim((string) config('openobserve.url'), '/');
        try {
            $response = Http::timeout(2)->get("{$ooUrl}/healthz");
            $ok = $response->successful() || $response->status() < 500;
            $points[] = ['name' => 'jvmeta_dep_up', 'value' => $ok ? 1 : 0, 'labels' => ['dependency' => 'openobserve']];
        } catch (Throwable) {
            $points[] = ['name' => 'jvmeta_dep_up', 'value' => 0, 'labels' => ['dependency' => 'openobserve']];
        }

        $mongoHost = (string) config('mongodb.host', '127.0.0.1');
        $mongoPort = (int) config('mongodb.port', 27017);
        $errno = 0;
        $errstr = '';
        $fp = @fsockopen($mongoHost, $mongoPort, $errno, $errstr, 1.0);
        $mongoUp = $fp !== false;
        $points[] = [
            'name' => 'jvmeta_dep_up',
            'value' => $mongoUp ? 1 : 0,
            'labels' => ['dependency' => 'mongo'],
        ];
        if ($mongoUp) {
            fclose($fp);
        }

        return $points;
    }

    /**
     * @param  list<int>  $sorted
     */
    private function percentile(array $sorted, float $p): float
    {
        if ($sorted === []) {
            return 0.0;
        }
        sort($sorted);
        $index = (int) max(0, min(count($sorted) - 1, (int) floor($p * (count($sorted) - 1))));

        return (float) $sorted[$index];
    }
}
