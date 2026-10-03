<?php

declare(strict_types=1);

namespace Tests\Feature\Contract;

use App\Models\CrawlEvent;
use App\Models\CrawlQueue;
use App\Models\Movie;
use App\Models\Source;
use JOOservices\Client\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Throwable;

/**
 * Generated failure responses go through the real crawlerx stack and the
 * jvmeta detail job. The expectations pin the crawlerx 1.2 contract: every
 * fetch failure is "blocked" (or "challenge") and the row fails on the first
 * attempt without a retry. When jvmeta moves to crawlerx 1.3 (terminal codes,
 * retryable / retryAfterSeconds; docs/01-projects/crawlerx/IMPROVEMENT-PLAN.md
 * lane JV1) these expectations change on purpose.
 */
final class CrawlerxFailureContractTest extends CrawlerxContractTestCase
{
    private const SLUG = 'onejav';

    private const URL = 'https://onejav.com/torrent/ymds282';

    /** @return array<string, array{\Closure(): (ResponseInterface|Throwable), string}> */
    public static function fetchFailures(): array
    {
        $challenge = '<html><head><title>Just a moment...</title></head><body>Checking your browser</body></html>';

        return [
            'not found 404' => [static fn() => TestResponse::make(404, [], fake()->sentence()), CrawlEvent::KIND_BLOCKED],
            'gone 410' => [static fn() => TestResponse::make(410, [], fake()->sentence()), CrawlEvent::KIND_BLOCKED],
            'rate limited 429 with Retry-After' => [static fn() => TestResponse::make(429, ['Retry-After' => '120'], fake()->sentence()), CrawlEvent::KIND_BLOCKED],
            'server error 500' => [static fn() => TestResponse::make(500, [], fake()->sentence()), CrawlEvent::KIND_BLOCKED],
            'unavailable 503 with Retry-After' => [static fn() => TestResponse::make(503, ['Retry-After' => '30'], fake()->sentence()), CrawlEvent::KIND_BLOCKED],
            'empty 200 body' => [static fn() => TestResponse::make(200, [], ''), CrawlEvent::KIND_BLOCKED],
            'network error' => [static fn() => new RuntimeException(fake()->sentence()), CrawlEvent::KIND_BLOCKED],
            'cloudflare challenge 403' => [static fn() => TestResponse::make(403, ['cf-mitigated' => 'challenge', 'Server' => 'cloudflare'], $challenge), CrawlEvent::KIND_CHALLENGE],
        ];
    }

    /** @param \Closure(): (ResponseInterface|Throwable) $response */
    #[DataProvider('fetchFailures')]
    public function test_fetch_failure_fails_the_row_without_storing(\Closure $response, string $eventKind): void
    {
        $row = $this->detailRow();
        $this->respondWith(self::URL, $response(), $response(), $response());

        $this->dispatchFor($row);

        $this->assertFailedOnce($row, $eventKind);
        self::assertStringContainsString('All fetch methods exhausted', (string) $row->refresh()->last_error);
    }

    public function test_page_without_movie_fields_is_parse_drift(): void
    {
        $row = $this->detailRow();
        $html = '<html><body><p>' . fake()->paragraph() . '</p></body></html>';
        $this->respondWith(self::URL, TestResponse::make(200, ['Content-Type' => 'text/html'], $html));

        $this->dispatchFor($row);

        $this->assertFailedOnce($row, CrawlEvent::KIND_PARSE_DRIFT);
        self::assertStringContainsString('did not contain expected movie fields', (string) $row->refresh()->last_error);
    }

    private function detailRow(): CrawlQueue
    {
        $this->source(self::SLUG);

        return $this->claimedRow(self::SLUG, self::URL, CrawlQueue::KIND_DETAIL);
    }

    private function assertFailedOnce(CrawlQueue $row, string $eventKind): void
    {
        $row->refresh();
        self::assertSame(CrawlQueue::STATUS_FAILED, $row->status);
        self::assertSame(1, $row->attempts);
        self::assertNull($row->next_attempt_at, 'crawlerx 1.2 gives no retry hint, so jvmeta does not reschedule');
        self::assertSame(0, Movie::query()->count());
        $this->assertDatabaseHas('crawl_events', ['source_slug' => self::SLUG, 'kind' => $eventKind]);
        self::assertSame(1, Source::query()->where('slug', self::SLUG)->value('consecutive_failures'));
    }
}
