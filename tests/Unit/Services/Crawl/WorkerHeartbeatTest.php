<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Crawl;

use App\Services\Crawl\WorkerHeartbeat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

final class WorkerHeartbeatTest extends TestCase
{
    use RefreshDatabase;
    public function test_beat_writes_global_and_instance_heartbeat(): void
    {
        $heartbeat = app(WorkerHeartbeat::class);
        Carbon::setTestNow('2026-09-30 10:00:00');
        $heartbeat->beat('node-a:100');

        $this->assertSame('2026-09-30T10:00:00+00:00', $heartbeat->lastBeatAt()?->toIso8601String());
        $this->assertSame('2026-09-30T10:00:00+00:00', $heartbeat->lastBeatAt('node-a:100')?->toIso8601String());
        Carbon::setTestNow();
    }

    public function test_is_stale_true_when_no_heartbeat(): void
    {
        $heartbeat = app(WorkerHeartbeat::class);

        $this->assertTrue($heartbeat->isStale());
        $this->assertTrue($heartbeat->isStale('node-a:100'));
    }

    public function test_is_stale_respects_threshold(): void
    {
        $heartbeat = app(WorkerHeartbeat::class);
        Carbon::setTestNow('2026-09-30 10:00:00');
        $heartbeat->beat('node-a:100');

        Carbon::setTestNow('2026-09-30 10:15:00');

        $this->assertFalse($heartbeat->isStale('node-a:100', 900));
        $this->assertTrue($heartbeat->isStale('node-a:100', 60));
        Carbon::setTestNow();
    }

    public function test_instances_lists_each_worker_with_staleness(): void
    {
        $heartbeat = app(WorkerHeartbeat::class);
        Carbon::setTestNow('2026-09-30 10:00:00');
        $heartbeat->beat('node-a:100');
        $heartbeat->beat('node-b:200');

        Carbon::setTestNow('2026-09-30 12:00:00');
        $heartbeat->beat('node-b:200');

        $instances = $heartbeat->instances(600);

        $this->assertCount(2, $instances);
        $this->assertSame('node-a:100', $instances[0]['instance']);
        $this->assertTrue($instances[0]['stale']);
        $this->assertSame('node-b:200', $instances[1]['instance']);
        $this->assertFalse($instances[1]['stale']);
        Carbon::setTestNow();
    }
}
