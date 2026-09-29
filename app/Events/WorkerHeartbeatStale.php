<?php

declare(strict_types=1);

namespace App\Events;

/**
 * Watchdog detected no crawl worker heartbeat within the configured window.
 */
final class WorkerHeartbeatStale
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(public readonly array $context = []) {}
}
