<?php

declare(strict_types=1);

namespace Tests\Feature\Crawl;

use App\Models\CrawlEvent;
use App\Models\CrawlQueue;
use App\Models\CrawlRun;
use App\Models\Source;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CrawlTickCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_tick_syncs_configured_sources_and_enqueues_listing_urls(): void
    {
        $this->artisan('crawl:tick', ['--source' => 'javdb', '--limit' => 1])->assertSuccessful();

        $this->assertDatabaseHas('sources', [
            'slug' => 'javdb',
            'base_url' => 'https://javdb.com',
            'enabled' => true,
            'priority' => 10,
            'needs_proxy' => false,
        ]);
        $this->assertDatabaseHas('crawl_queue', [
            'source_slug' => 'javdb',
            'url' => 'https://javdb.com/',
            'kind' => CrawlQueue::KIND_LISTING,
            'status' => CrawlQueue::STATUS_PENDING,
        ]);
        $this->assertSame(1, CrawlRun::query()->where('source_slug', 'javdb')->count());
        $this->assertSame(1, CrawlEvent::query()->where('source_slug', 'javdb')->where('kind', 'queue_tick')->count());
    }

    public function test_open_circuit_source_does_not_block_other_sources(): void
    {
        Source::factory()->create([
            'slug' => 'javdb',
            'circuit_state' => Source::CIRCUIT_OPEN,
            'circuit_opened_at' => now(),
        ]);

        $this->artisan('crawl:tick', ['--limit' => 1])->assertSuccessful();

        $this->assertDatabaseMissing('crawl_queue', ['source_slug' => 'javdb']);
        $this->assertDatabaseHas('crawl_queue', ['source_slug' => 'javdatabase']);
    }

    public function test_tick_deduplicates_listing_urls_across_runs(): void
    {
        $this->artisan('crawl:tick', ['--source' => 'javdb', '--limit' => 1])->assertSuccessful();
        $this->artisan('crawl:tick', ['--source' => 'javdb', '--limit' => 1])->assertSuccessful();

        $this->assertSame(1, CrawlQueue::query()->where('source_slug', 'javdb')->where('url', 'https://javdb.com/')->count());
    }

    public function test_tick_enqueues_performer_listing_for_warashi(): void
    {
        $this->artisan('crawl:tick', ['--source' => 'warashi', '--limit' => 1])->assertSuccessful();

        $this->assertDatabaseHas('crawl_queue', [
            'source_slug' => 'warashi',
            'url' => 'https://warashi-asian-pornstars.fr/en/s-2-2/female-pornstars/toutes/all/page/1',
            'kind' => CrawlQueue::KIND_PERFORMER_LISTING,
            'status' => CrawlQueue::STATUS_PENDING,
        ]);
    }

    public function test_tick_enqueues_gallery_for_eporner(): void
    {
        $this->artisan('crawl:tick', ['--source' => 'eporner', '--limit' => 1])->assertSuccessful();

        $this->assertDatabaseHas('crawl_queue', [
            'source_slug' => 'eporner',
            'kind' => CrawlQueue::KIND_GALLERY,
            'status' => CrawlQueue::STATUS_PENDING,
        ]);
    }

    public function test_tick_splits_xcity_movie_and_performer_seeds(): void
    {
        $this->artisan('crawl:tick', ['--source' => 'xcity', '--limit' => 2])->assertSuccessful();

        $this->assertDatabaseHas('crawl_queue', [
            'source_slug' => 'xcity',
            'url' => 'https://xxx.xcity.jp/avod/list/?style=simple',
            'kind' => CrawlQueue::KIND_LISTING,
        ]);
        $this->assertDatabaseHas('crawl_queue', [
            'source_slug' => 'xcity',
            'url' => 'https://xxx.xcity.jp/idol/',
            'kind' => CrawlQueue::KIND_PERFORMER_LISTING,
        ]);
    }
}
