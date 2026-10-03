<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Models\CrawlQueue;
use App\Models\Movie;
use App\Models\MovieObservation;
use App\Models\MovieSource;
use MongoDB\BSON\ObjectId;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Live-captured detail page → real crawlerx → jvmeta job → Postgres (movie,
 * observations, source link) + Mongo archive + Elasticsearch document.
 */
final class MoviePipelineTest extends IntegrationTestCase
{
    /** @return array<string, array{string, string, string, string}> */
    public static function detailFixtures(): array
    {
        return self::crawlerxFixtures(CrawlQueue::KIND_DETAIL);
    }

    #[DataProvider('detailFixtures')]
    public function test_detail_page_is_stored_in_postgres_mongo_and_elasticsearch(string $slug, string $kind, string $url, string $bodyPath): void
    {
        $this->crawl($slug, $kind, $url, $bodyPath);

        $movie = Movie::query()->sole();
        $link = MovieSource::query()->where(['movie_id' => $movie->id, 'site_slug' => $slug])->sole();
        self::assertSame($url, $link->source_url);
        self::assertNotEmpty($link->mongo_id);

        $archived = $this->mongoCollection($slug)->findOne(['_id' => new ObjectId((string) $link->mongo_id)]);
        self::assertNotNull($archived, 'Mongo archive document missing');
        self::assertSame($url, $archived['url']);
        self::assertSame('movie', $archived['entity_type']);

        $document = $this->esDocument((string) config('elasticsearch.movies_index'), (string) $movie->uuid);
        self::assertNotNull($document, 'Elasticsearch movie document missing');
        self::assertSame($movie->code_normalized, $document['code_normalized']);
        self::assertCount(self::EMBED_DIM, $document['embedding_movie'] ?? []);
    }

    public function test_crawling_the_same_page_twice_does_not_duplicate_data(): void
    {
        [$slug, $kind, $url, $bodyPath] = self::detailFixtures()['onejav/detail-sample-1.html'];

        $row = $this->crawl($slug, $kind, $url, $bodyPath);
        $observations = MovieObservation::query()->count();

        // A recrawl reuses the queue row ((source_slug, url) is unique).
        $row->update(['status' => CrawlQueue::STATUS_CLAIMED]);
        $this->respondWithFixture($url, $bodyPath);
        $this->dispatchFor($row);
        self::assertSame(CrawlQueue::STATUS_DONE, $row->refresh()->status, "recrawl failed: {$row->last_error}");

        self::assertSame(1, Movie::query()->count());
        self::assertSame(1, MovieSource::query()->count());
        // movie_observations is append-only by design (ARCHITECTURE.md): one row per field per crawl.
        self::assertSame(2 * $observations, MovieObservation::query()->count());
        self::assertSame(1, $this->mongoCollection($slug)->countDocuments());
        self::assertSame(1, $this->esCount((string) config('elasticsearch.movies_index')));
    }

    private function crawl(string $slug, string $kind, string $url, string $bodyPath): CrawlQueue
    {
        $this->source($slug);
        $row = $this->claimedRow($slug, $url, $kind);
        $this->respondWithFixture($url, $bodyPath);

        $this->dispatchFor($row);

        $row->refresh();
        self::assertSame(CrawlQueue::STATUS_DONE, $row->status, "row failed: {$row->last_error}");

        return $row;
    }
}
