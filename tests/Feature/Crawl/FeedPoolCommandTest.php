<?php

declare(strict_types=1);

namespace Tests\Feature\Crawl;

use App\Console\Commands\Crawler\FeedPoolCommand;
use App\Jobs\FetchDetailJob;
use App\Jobs\FetchListingJob;
use App\Models\CrawlQueue;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class FeedPoolCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_feed_pool_command_has_legacy_dispatch_alias_and_limit_default(): void
    {
        $commands = app(Kernel::class)->all();
        $legacyAlias = $commands['crawler:feed-pool']->getAliases()[0] ?? null;

        $this->assertArrayHasKey('crawler:feed-pool', $commands);
        $this->assertIsString($legacyAlias);
        $this->assertArrayHasKey($legacyAlias, $commands);
        $this->assertInstanceOf(FeedPoolCommand::class, $commands['crawler:feed-pool']);
        $this->assertSame($commands['crawler:feed-pool'], $commands[$legacyAlias]);
        $this->assertSame('50', $commands['crawler:feed-pool']->getDefinition()->getOption('limit')->getDefault());
    }

    public function test_feed_pool_claims_and_dispatches_only_up_to_the_requested_limit(): void
    {
        $first = CrawlQueue::factory()->create([
            'source_slug' => fake()->slug(2),
            'kind' => CrawlQueue::KIND_LISTING,
        ]);
        $second = CrawlQueue::factory()->create([
            'source_slug' => fake()->slug(2),
            'kind' => CrawlQueue::KIND_DETAIL,
        ]);
        Queue::fake();

        $this->artisan('crawler:feed-pool', ['--limit' => 1])->assertSuccessful();

        Queue::assertPushed(FetchListingJob::class, 1);
        Queue::assertNotPushed(FetchDetailJob::class);
        $this->assertSame(CrawlQueue::STATUS_CLAIMED, $first->refresh()->status);
        $this->assertSame(CrawlQueue::STATUS_PENDING, $second->refresh()->status);
    }
}
