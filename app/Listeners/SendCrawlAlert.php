<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\CrawlSourceUnhealthy;
use App\Services\Alerts\AlertDispatcher;

final class SendCrawlAlert
{
    public function __construct(private readonly AlertDispatcher $alerts) {}

    public function handle(CrawlSourceUnhealthy $event): void
    {
        $this->alerts->send(
            debounceKey: "source:{$event->sourceSlug}:{$event->reason}",
            subject: "Source unhealthy: {$event->sourceSlug}",
            body: "Reason: {$event->reason}",
            context: array_merge(['source_slug' => $event->sourceSlug], $event->context),
        );
    }
}
