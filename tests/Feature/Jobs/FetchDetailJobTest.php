<?php

declare(strict_types=1);

namespace Tests\Feature\Jobs;

use App\Jobs\FetchDetailJob;
use App\Models\CrawlEvent;
use App\Models\CrawlQueue;
use App\Models\Source;
use App\Models\Movie;
use App\Services\Crawl\MovieDraftSink;
use App\Services\Crawler\CrawlerxClient;
use App\Services\Crawler\CrawlerxFetchResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use JOOservices\CrawlerX\Dto\Entity\MovieDto;
use Tests\Support\FakeCrawlerxClient;
use Tests\Support\FakeMovieDraftSink;
use Tests\TestCase;

final class FetchDetailJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_success_delivers_draft_to_sink_and_marks_row_done(): void
    {
        $source = $this->source('onejav');
        $row = $this->detailRow($source);
        $sink = new FakeMovieDraftSink();
        $this->app->instance(MovieDraftSink::class, $sink);

        $this->fakeClient(CrawlerxFetchResult::movie($this->movie()));

        FetchDetailJob::dispatch($row->id);

        self::assertCount(1, $sink->drafts);
        $draft = $sink->drafts[0];
        self::assertSame('onejav', $draft->sourceSlug);
        self::assertSame('https://onejav.com/torrent/ymds282', $draft->sourceUrl);
        self::assertSame('YMDS-282', $draft->code);
        self::assertSame('Sample Title', $draft->titleJp);
        self::assertSame(120, $draft->runtimeMinutes);
        self::assertSame([['url' => 'https://onejav.com/dl/ymds282']], $draft->extras['magnets']);
        self::assertNotNull($draft->crawledAt);

        $this->assertDatabaseHas('crawl_queue', ['id' => $row->id, 'status' => CrawlQueue::STATUS_DONE]);
        $this->assertDatabaseHas('sources', ['slug' => 'onejav', 'circuit_state' => Source::CIRCUIT_CLOSED, 'consecutive_failures' => 0]);
        self::assertNotNull($source->refresh()->last_success_at);
        $this->assertSame(0, Movie::query()->count());
    }

    public function test_success_persists_movie_through_container_bound_sink(): void
    {
        $source = $this->source('onejav');
        $row = $this->detailRow($source);

        $this->fakeClient(CrawlerxFetchResult::movie($this->movie()));

        FetchDetailJob::dispatch($row->id);

        $this->assertSame(1, Movie::query()->count());
        $movie = Movie::query()->firstOrFail();
        $this->assertSame('YMDS282', $movie->code_normalized);
        $this->assertDatabaseHas('movie_observations', ['movie_id' => $movie->id, 'field' => 'title_jp', 'value' => 'Sample Title']);
        $this->assertDatabaseHas('movie_observations', ['movie_id' => $movie->id, 'field' => 'runtime', 'value' => '120']);
        $this->assertDatabaseHas('movie_codes', ['movie_id' => $movie->id, 'code_normalized' => 'YMDS282', 'kind' => 'dvd']);
        $this->assertDatabaseHas('movie_media', ['movie_id' => $movie->id, 'kind' => 'magnet', 'url' => 'https://onejav.com/dl/ymds282']);
        $this->assertDatabaseHas('crawl_queue', ['id' => $row->id, 'status' => CrawlQueue::STATUS_DONE]);
    }

    public function test_crawl_failure_never_creates_movie_and_fails_row(): void
    {
        $source = $this->source('onejav');
        $row = $this->detailRow($source);
        $sink = new FakeMovieDraftSink();
        $this->app->instance(MovieDraftSink::class, $sink);

        $this->fakeClient(CrawlerxFetchResult::failure(CrawlerxClient::ERROR_BLOCKED, 'Blocked by site.'));

        FetchDetailJob::dispatch($row->id);

        self::assertSame([], $sink->drafts);
        $this->assertSame(0, Movie::query()->count());
        $this->assertDatabaseHas('crawl_events', ['source_slug' => 'onejav', 'kind' => CrawlEvent::KIND_BLOCKED]);
        $this->assertDatabaseHas('crawl_queue', ['id' => $row->id, 'status' => CrawlQueue::STATUS_FAILED, 'attempts' => 1]);
        $this->assertDatabaseHas('sources', ['slug' => 'onejav', 'consecutive_failures' => 1]);
    }

    public function test_normalization_failure_records_parse_drift_and_never_delivers_draft(): void
    {
        $source = $this->source('onejav');
        $row = CrawlQueue::factory()->create([
            'source_slug' => $source->slug,
            'url' => 'https://onejav.com/torrent/no-extractable-code',
            'kind' => CrawlQueue::KIND_DETAIL,
            'status' => CrawlQueue::STATUS_CLAIMED,
            'max_attempts' => 3,
        ]);
        $sink = new FakeMovieDraftSink();
        $this->app->instance(MovieDraftSink::class, $sink);

        $movie = new MovieDto(title: 'No Code Here');
        $this->fakeClient(CrawlerxFetchResult::movie($movie));

        FetchDetailJob::dispatch($row->id);

        self::assertSame([], $sink->drafts);
        $this->assertSame(0, Movie::query()->count());
        $this->assertDatabaseHas('crawl_events', ['source_slug' => 'onejav', 'kind' => CrawlEvent::KIND_PARSE_DRIFT]);
        $this->assertDatabaseHas('crawl_queue', ['id' => $row->id, 'status' => CrawlQueue::STATUS_FAILED]);
        $this->assertDatabaseHas('sources', ['slug' => 'onejav', 'consecutive_failures' => 1]);
    }

    public function test_missing_normalizer_fails_row_with_parse_drift(): void
    {
        $source = Source::factory()->create(['slug' => 'mystery', 'base_url' => 'https://mystery.test']);
        $row = CrawlQueue::factory()->create([
            'source_slug' => 'mystery',
            'url' => 'https://mystery.test/v/1',
            'kind' => CrawlQueue::KIND_DETAIL,
            'status' => CrawlQueue::STATUS_CLAIMED,
            'max_attempts' => 3,
        ]);

        $this->fakeClient(CrawlerxFetchResult::movie($this->movie()));

        FetchDetailJob::dispatch($row->id);

        $this->assertDatabaseHas('crawl_events', ['source_slug' => 'mystery', 'kind' => CrawlEvent::KIND_PARSE_DRIFT]);
        $this->assertDatabaseHas('crawl_queue', ['id' => $row->id, 'status' => CrawlQueue::STATUS_FAILED]);
        $this->assertDatabaseHas('sources', ['slug' => 'mystery', 'consecutive_failures' => 1]);
    }

    public function test_unknown_source_slug_fails_row_without_throttle_or_circuit_touch(): void
    {
        $row = CrawlQueue::factory()->create([
            'source_slug' => 'ghost',
            'url' => 'https://ghost.test/v/1',
            'kind' => CrawlQueue::KIND_DETAIL,
            'status' => CrawlQueue::STATUS_CLAIMED,
        ]);

        $this->fakeClient(CrawlerxFetchResult::movie($this->movie()));

        FetchDetailJob::dispatch($row->id);

        $this->assertDatabaseHas('crawl_events', ['source_slug' => 'ghost', 'kind' => CrawlEvent::KIND_PARSE_DRIFT]);
        $this->assertDatabaseHas('crawl_queue', ['id' => $row->id, 'status' => CrawlQueue::STATUS_FAILED]);
        $this->assertDatabaseMissing('sources', ['slug' => 'ghost']);
    }

    private function source(string $slug): Source
    {
        return Source::factory()->create([
            'slug' => $slug,
            'base_url' => 'https://onejav.com',
            'gap_seconds_default' => 2.0,
            'gap_seconds_min' => 0.5,
            'gap_seconds_max' => 15.0,
            'gap_seconds_current' => 2.0,
        ]);
    }

    private function detailRow(Source $source): CrawlQueue
    {
        return CrawlQueue::factory()->create([
            'source_slug' => $source->slug,
            'url' => 'https://onejav.com/torrent/ymds282',
            'kind' => CrawlQueue::KIND_DETAIL,
            'status' => CrawlQueue::STATUS_CLAIMED,
            'max_attempts' => 3,
        ]);
    }

    private function movie(): MovieDto
    {
        return new MovieDto(
            externalId: 'ymds282',
            title: 'Sample Title',
            code: 'YMDS-282',
            coverUrl: 'https://onejav.com/cover.jpg',
            date: '2024-05-15',
            duration: 120,
            performers: [],
            tags: ['Drama'],
            metadata: ['download_url' => 'https://onejav.com/dl/ymds282'],
        );
    }

    private function fakeClient(CrawlerxFetchResult $detail): void
    {
        $this->app->instance(
            CrawlerxClient::class,
            new FakeCrawlerxClient(CrawlerxFetchResult::failure(CrawlerxClient::ERROR_PARSE_FAILED, 'unused'), $detail),
        );
    }
}
