<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Crawl;

use App\Jobs\FetchDetailJob;
use App\Jobs\FetchGalleryJob;
use App\Jobs\FetchListingJob;
use App\Jobs\FetchPerformerDetailJob;
use App\Jobs\FetchPerformerListingJob;
use App\Models\CrawlQueue;
use App\Support\Crawl\LaravelCrawlQueue;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class LaravelCrawlQueueTest extends TestCase
{
    public function test_all_kinds_map_to_named_queues(): void
    {
        $this->assertSame(
            ['listing', 'detail', 'performer_listing', 'performer_detail', 'gallery'],
            LaravelCrawlQueue::allNames(),
        );
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function kindProvider(): array
    {
        return [
            'listing' => [CrawlQueue::KIND_LISTING, 'listing'],
            'detail' => [CrawlQueue::KIND_DETAIL, 'detail'],
            'performer_listing' => [CrawlQueue::KIND_PERFORMER_LISTING, 'performer_listing'],
            'performer_detail' => [CrawlQueue::KIND_PERFORMER_DETAIL, 'performer_detail'],
            'gallery' => [CrawlQueue::KIND_GALLERY, 'gallery'],
        ];
    }

    #[DataProvider('kindProvider')]
    public function test_name_for_kind(string $kind, string $queue): void
    {
        $this->assertSame($queue, LaravelCrawlQueue::nameForKind($kind));
    }

    public function test_jobs_bind_matching_queue_names(): void
    {
        $this->assertSame('listing', (new FetchListingJob(1))->queue);
        $this->assertSame('detail', (new FetchDetailJob(1))->queue);
        $this->assertSame('performer_listing', (new FetchPerformerListingJob(1))->queue);
        $this->assertSame('performer_detail', (new FetchPerformerDetailJob(1))->queue);
        $this->assertSame('gallery', (new FetchGalleryJob(1))->queue);
    }
}
