<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Crawl;

use App\Models\CrawlQueue;
use App\Models\Source;
use App\Services\Crawl\CrawlQueueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CrawlQueueServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_duplicate_source_url_is_not_enqueued_twice(): void
    {
        $source = Source::factory()->create();
        $url = fake()->url();
        $service = new CrawlQueueService();

        $first = $service->enqueue($url, $source->slug, CrawlQueue::KIND_LISTING);
        $second = $service->enqueue($url, $source->slug, CrawlQueue::KIND_LISTING);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, CrawlQueue::query()->where('source_slug', $source->slug)->where('url', $url)->count());
    }

    public function test_stale_claimed_rows_are_reclaimed_to_pending_when_attempts_remain(): void
    {
        $row = CrawlQueue::factory()->create([
            'status' => CrawlQueue::STATUS_CLAIMED,
            'attempts' => 1,
            'max_attempts' => 3,
            'claimed_at' => now()->subHour(),
            'locked_by' => fake()->uuid(),
        ]);

        $reclaimed = (new CrawlQueueService())->reclaimStaleClaimed(900);

        $this->assertSame(1, $reclaimed);
        $this->assertSame(CrawlQueue::STATUS_PENDING, $row->refresh()->status);
        $this->assertNull($row->claimed_at);
        $this->assertNull($row->locked_by);
    }

    public function test_stale_claimed_rows_exhausted_by_max_attempts_are_failed(): void
    {
        $row = CrawlQueue::factory()->create([
            'status' => CrawlQueue::STATUS_CLAIMED,
            'attempts' => 3,
            'max_attempts' => 3,
            'claimed_at' => now()->subHour(),
        ]);

        $reclaimed = (new CrawlQueueService())->reclaimStaleClaimed(900);

        $this->assertSame(1, $reclaimed);
        $this->assertSame(CrawlQueue::STATUS_FAILED, $row->refresh()->status);
    }
}
