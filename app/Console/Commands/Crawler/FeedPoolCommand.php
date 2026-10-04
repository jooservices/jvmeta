<?php

declare(strict_types=1);

namespace App\Console\Commands\Crawler;

use App\Jobs\FetchDetailJob;
use App\Jobs\FetchGalleryJob;
use App\Jobs\FetchListingJob;
use App\Jobs\FetchPerformerDetailJob;
use App\Jobs\FetchPerformerListingJob;
use App\Models\CrawlQueue;
use App\Observability\ObservabilityEmitter;
use App\Observability\TraceContext;
use App\Services\Crawl\CrawlQueueService;
use Illuminate\Console\Command;

/**
 * Claims pending crawl_queue rows and dispatches Laravel jobs (buffer → worker pool).
 */
final class FeedPoolCommand extends Command
{
    protected $signature = 'crawler:feed-pool {--limit=50}';

    protected $aliases = ['crawl:dispatch'];

    protected $description = 'Claim pending crawl_queue rows and dispatch FetchListing/FetchDetail/Performer/Gallery jobs.';

    public function handle(CrawlQueueService $queue, ObservabilityEmitter $observability): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $workerId = gethostname() . ':' . getmypid();
        $dispatched = 0;
        $traceId = TraceContext::newTraceId();
        $parentSpanId = TraceContext::newSpanId();
        $traceparent = TraceContext::formatTraceparent($traceId, $parentSpanId);

        for ($i = 0; $i < $limit; $i++) {
            $row = $queue->claimNext($workerId);
            if (! $row instanceof CrawlQueue) {
                break;
            }

            match ($row->kind) {
                CrawlQueue::KIND_LISTING => FetchListingJob::dispatch($row->id, $traceparent),
                CrawlQueue::KIND_DETAIL => FetchDetailJob::dispatch($row->id, $traceparent),
                CrawlQueue::KIND_PERFORMER_LISTING => FetchPerformerListingJob::dispatch($row->id, $traceparent),
                CrawlQueue::KIND_PERFORMER_DETAIL => FetchPerformerDetailJob::dispatch($row->id, $traceparent),
                CrawlQueue::KIND_GALLERY => FetchGalleryJob::dispatch($row->id, $traceparent),
                default => null,
            };

            $dispatched++;
        }

        $observability->emitOps('crawl_dispatch', ['dispatched' => $dispatched]);
        $this->components->info("Dispatched {$dispatched} crawl job(s).");

        return self::SUCCESS;
    }
}
