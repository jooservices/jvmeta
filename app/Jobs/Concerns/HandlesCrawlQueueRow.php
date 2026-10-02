<?php

declare(strict_types=1);

namespace App\Jobs\Concerns;

use App\Events\CrawlIncidentOccurred;
use App\Models\CrawlEvent;
use App\Models\CrawlQueue;
use App\Observability\ObservabilityEmitter;
use App\Models\Source;
use App\Services\Crawl\Soft404Detector;
use App\Services\Crawl\SourceCircuitBreaker;
use App\Services\Crawl\SourceThrottle;
use App\Services\Crawl\TitleFailureTracker;
use App\Services\Crawler\CrawlerxClient;
use App\Services\Crawler\CrawlerxFetchResult;
use Illuminate\Support\Facades\Event;

/**
 * Shared crawl_queue row bookkeeping for the fetch jobs: row lookup, terminal
 * state transitions, crawl event recording, and the per-source failure
 * bookkeeping (attempts, throttle, circuit breaker).
 */
trait HandlesCrawlQueueRow
{
    private function queueRow(int $id, string $expectedKind): ?CrawlQueue
    {
        $row = CrawlQueue::query()->find($id);

        if (! $row instanceof CrawlQueue || $row->status !== CrawlQueue::STATUS_CLAIMED) {
            $kind = $expectedKind;
            $status = 'missing';
            if ($row instanceof CrawlQueue) {
                $kind = $row->kind;
                $status = $row->status;
            }

            app(ObservabilityEmitter::class)->emitOps('crawl_job_skipped', [
                'crawl_queue_id' => $id,
                'kind' => $kind,
                'status' => $status,
                'job' => static::class,
            ]);

            return null;
        }

        return $row;
    }

    private function markDone(CrawlQueue $row): void
    {
        $row->forceFill([
            'status' => CrawlQueue::STATUS_DONE,
            'claimed_at' => null,
            'locked_by' => null,
            'last_error' => null,
        ])->save();
    }

    private function markFailed(CrawlQueue $row, ?string $error): void
    {
        $row->forceFill([
            'status' => CrawlQueue::STATUS_FAILED,
            'attempts' => $row->attempts + 1,
            'claimed_at' => null,
            'locked_by' => null,
            'last_error' => $error,
        ])->save();
    }

    /**
     * @param  array<string, mixed>  $detail
     */
    private function recordEvent(string $sourceSlug, string $kind, ?string $url, array $detail): void
    {
        Event::dispatch(new CrawlIncidentOccurred($sourceSlug, $kind, $url, $detail));
    }

    private function onCrawlFailure(
        CrawlQueue $row,
        Source $source,
        CrawlerxFetchResult $result,
        SourceCircuitBreaker $breaker,
        SourceThrottle $throttle,
    ): void {
        $errorCode = $result->errorCode ?? CrawlerxClient::ERROR_PARSE_FAILED;
        $message = $result->errorMessage ?? 'Crawl failed.';
        $soft404 = app(Soft404Detector::class)->matches($message, $source)
            || $errorCode === CrawlerxClient::ERROR_SOFT404;

        $this->recordEvent($row->source_slug, $this->eventKind($errorCode, $soft404), $row->url, [
            'error_code' => $errorCode,
            'error' => $message,
            'soft404' => $soft404,
        ]);
        $this->markFailed($row, $message);
        $breaker->recordFailure($source, $message);
        $throttle->onFailure($source);

        if (in_array($row->kind, [CrawlQueue::KIND_DETAIL, CrawlQueue::KIND_PERFORMER_DETAIL], true)) {
            app(TitleFailureTracker::class)->recordFailure(
                $row->source_slug,
                $row->url,
                $message,
                $soft404,
            );
        }
    }

    private function onCrawlSuccess(CrawlQueue $row): void
    {
        if ($row->kind === CrawlQueue::KIND_DETAIL) {
            app(TitleFailureTracker::class)->recordSuccess($row->source_slug, $row->url);
        }
    }

    private function eventKind(string $errorCode, bool $soft404 = false): string
    {
        if ($soft404) {
            return CrawlEvent::KIND_SOFT404;
        }

        return match ($errorCode) {
            CrawlerxClient::ERROR_BLOCKED => CrawlEvent::KIND_BLOCKED,
            CrawlerxClient::ERROR_CHALLENGE => CrawlEvent::KIND_CHALLENGE,
            CrawlerxClient::ERROR_SOFT404 => CrawlEvent::KIND_SOFT404,
            default => CrawlEvent::KIND_PARSE_DRIFT,
        };
    }
}
