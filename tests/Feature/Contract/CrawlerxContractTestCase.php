<?php

declare(strict_types=1);

namespace Tests\Feature\Contract;

use App\Jobs\FetchDetailJob;
use App\Jobs\FetchGalleryJob;
use App\Jobs\FetchListingJob;
use App\Jobs\FetchPerformerDetailJob;
use App\Jobs\FetchPerformerListingJob;
use App\Models\CrawlQueue;
use App\Models\Source;
use Illuminate\Foundation\Testing\RefreshDatabase;
use JOOservices\Client\Client\ClientBuilder;
use JOOservices\Client\Testing\HttpFakeRegistry;
use JOOservices\Client\Testing\TestResponseSequence;
use Psr\Http\Message\ResponseInterface;
use Tests\TestCase;
use Throwable;

/**
 * Runs the real jooservices/crawlerx stack inside jvmeta jobs. Only the HTTP
 * transport is faked (jooservices/client ClientBuilder::fake()), so adapters,
 * parsers and the jvmeta normalize/persist pipeline all execute for real.
 * While the client is faked, crawlerx uses its HTTP-only fetch chain.
 */
abstract class CrawlerxContractTestCase extends TestCase
{
    use RefreshDatabase;

    protected HttpFakeRegistry $http;

    protected function setUp(): void
    {
        parent::setUp();

        // Unmatched requests get the registry default (404); tests assert the
        // primary URL explicitly through recorded().
        $this->http = ClientBuilder::fake();
    }

    protected function tearDown(): void
    {
        ClientBuilder::clearFake();

        parent::tearDown();
    }

    protected function respondWith(string $url, ResponseInterface|Throwable ...$responses): void
    {
        $sequence = new TestResponseSequence();
        foreach ($responses as $response) {
            $sequence->push($response);
        }

        ClientBuilder::respond('GET', $url, $sequence);
    }

    protected function source(string $slug): Source
    {
        $baseUrl = config("jvmeta_sources.sources.{$slug}.base_url");

        return Source::factory()->create([
            'slug' => $slug,
            'base_url' => is_string($baseUrl) ? $baseUrl : 'https://' . $slug . '.test',
        ]);
    }

    protected function claimedRow(string $slug, string $url, string $kind): CrawlQueue
    {
        return CrawlQueue::factory()->create([
            'source_slug' => $slug,
            'url' => $url,
            'kind' => $kind,
            'status' => CrawlQueue::STATUS_CLAIMED,
            'max_attempts' => 3,
        ]);
    }

    protected function dispatchFor(CrawlQueue $row): void
    {
        match ($row->kind) {
            CrawlQueue::KIND_LISTING => FetchListingJob::dispatch($row->id),
            CrawlQueue::KIND_DETAIL => FetchDetailJob::dispatch($row->id),
            CrawlQueue::KIND_PERFORMER_LISTING => FetchPerformerListingJob::dispatch($row->id),
            CrawlQueue::KIND_PERFORMER_DETAIL => FetchPerformerDetailJob::dispatch($row->id),
            CrawlQueue::KIND_GALLERY => FetchGalleryJob::dispatch($row->id),
        };
    }

    /** @return list<string> */
    protected function requestedUrls(): array
    {
        return array_map(
            static fn($recorded): string => (string) $recorded->request->getUri(),
            $this->http->recorded(),
        );
    }
}
