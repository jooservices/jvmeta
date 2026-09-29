<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Events\CrawlIncidentOccurred;
use App\Models\CrawlQueue;
use App\Models\CrawlRun;
use App\Models\Source;
use App\Observability\ObservabilityEmitter;
use App\Services\Crawl\CrawlQueueService;
use App\Services\Crawl\SourceCircuitBreaker;
use App\Services\Crawl\SourceThrottle;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Event;

final class CrawlTickCommand extends Command
{
    protected $signature = 'crawl:tick {--source=} {--limit=50}';

    protected $description = 'Enqueue crawl listing URLs for enabled JVMeta sources.';

    public function handle(
        CrawlQueueService $queue,
        SourceCircuitBreaker $breaker,
        SourceThrottle $throttle,
        ObservabilityEmitter $observability,
    ): int {
        $this->syncSources();

        $limit = max(1, (int) $this->option('limit'));
        $sourceFilter = $this->option('source');

        $reclaimed = $queue->reclaimStaleClaimed((int) config('jvmeta_sources.defaults.queue.stale_claim_timeout_seconds', 900));
        if ($reclaimed > 0) {
            $this->components->info("Reclaimed {$reclaimed} stale queue rows.");
            $observability->emitOps('queue_reclaim', ['reclaimed' => $reclaimed]);
        }

        $sources = Source::query()
            ->where('enabled', true)
            ->when(is_string($sourceFilter) && $sourceFilter !== '', fn($query) => $query->where('slug', $sourceFilter))
            ->orderBy('priority')
            ->get();

        foreach ($sources as $source) {
            if (! $breaker->isAvailable($source)) {
                continue;
            }

            $this->enqueueSourceSeeds($source, $limit, $queue, $throttle, $observability);
        }

        return self::SUCCESS;
    }

    private function enqueueSourceSeeds(
        Source $source,
        int $limit,
        CrawlQueueService $queue,
        SourceThrottle $throttle,
        ObservabilityEmitter $observability,
    ): void {
        $run = CrawlRun::query()->create([
            'source_slug' => $source->slug,
            'started_at' => now(),
            'status' => 'running',
            'pages_fetched' => 0,
            'movies_new' => 0,
            'movies_updated' => 0,
            'failures' => 0,
            'proxy_requests' => 0,
            'proxy_bytes' => 0,
            'browser_fetches' => 0,
        ]);

        $queued = 0;
        $seeds = array_slice($this->seedUrls($source->slug), 0, $limit);

        foreach ($seeds as [$kind, $url]) {
            $beforeExists = CrawlQueue::query()->where('source_slug', $source->slug)->where('url', $url)->exists();
            $queue->enqueue($url, $source->slug, $kind);

            if (! $beforeExists) {
                $queued++;
            }
        }

        Event::dispatch(new CrawlIncidentOccurred(
            sourceSlug: $source->slug,
            kind: 'queue_tick',
            url: null,
            detail: ['queued' => $queued, 'gap_seconds' => $throttle->currentGap($source)],
        ));

        $run->forceFill([
            'status' => 'completed',
            'finished_at' => now(),
        ])->save();
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    private function seedUrls(string $sourceSlug): array
    {
        $seeds = [];

        foreach ($this->configUrlList($sourceSlug, 'movie_listing_urls', 'listing_urls') as $url) {
            $seeds[] = [CrawlQueue::KIND_LISTING, $url];
        }

        foreach ($this->configUrlList($sourceSlug, 'performer_listing_urls') as $url) {
            $seeds[] = [CrawlQueue::KIND_PERFORMER_LISTING, $url];
        }

        foreach ($this->configUrlList($sourceSlug, 'gallery_urls') as $url) {
            $seeds[] = [CrawlQueue::KIND_GALLERY, $url];
        }

        return $seeds;
    }

    /** @return list<string> */
    private function configUrlList(string $sourceSlug, string $primaryKey, ?string $fallbackKey = null): array
    {
        $urls = config("jvmeta_sources.sources.{$sourceSlug}.{$primaryKey}", []);
        if ((! is_array($urls) || $urls === []) && $fallbackKey !== null) {
            $urls = config("jvmeta_sources.sources.{$sourceSlug}.{$fallbackKey}", []);
        }

        if (! is_array($urls)) {
            return [];
        }

        return array_values(array_filter($urls, is_string(...)));
    }

    private function syncSources(): void
    {
        $sources = config('jvmeta_sources.sources', []);
        if (! is_array($sources) || $sources === []) {
            return;
        }

        foreach ($sources as $slug => $source) {
            if (! is_string($slug) || ! is_array($source)) {
                continue;
            }

            $defaultGap = (float) Arr::get($source, 'gap_seconds_default', config('jvmeta_sources.defaults.fallback_gap_seconds.default', 30));
            $existing = Source::query()->find($slug);
            Source::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => (string) Arr::get($source, 'name'),
                    'base_url' => (string) Arr::get($source, 'base_url'),
                    'enabled' => (bool) Arr::get($source, 'enabled', config('jvmeta_sources.defaults.enabled', true)),
                    'priority' => (int) Arr::get($source, 'priority'),
                    'needs_proxy' => (bool) Arr::get($source, 'needs_proxy', config('jvmeta_sources.defaults.needs_proxy', false)),
                    'gap_seconds_default' => $defaultGap,
                    'gap_seconds_min' => (float) Arr::get($source, 'gap_seconds_min', config('jvmeta_sources.defaults.fallback_gap_seconds.min', 30)),
                    'gap_seconds_max' => (float) Arr::get($source, 'gap_seconds_max', config('jvmeta_sources.defaults.fallback_gap_seconds.max', 120)),
                    'gap_seconds_current' => $existing instanceof Source ? $existing->gap_seconds_current : $defaultGap,
                    'consecutive_failures' => $existing instanceof Source ? $existing->consecutive_failures : 0,
                    'circuit_state' => $existing instanceof Source ? $existing->circuit_state : Source::CIRCUIT_CLOSED,
                    'circuit_opened_at' => $existing instanceof Source ? $existing->circuit_opened_at : null,
                    'last_success_at' => $existing instanceof Source ? $existing->last_success_at : null,
                    'last_error_at' => $existing instanceof Source ? $existing->last_error_at : null,
                    'last_error' => $existing instanceof Source ? $existing->last_error : null,
                    'soft404_markers' => $existing instanceof Source ? $existing->soft404_markers : null,
                ],
            );
        }
    }
}
