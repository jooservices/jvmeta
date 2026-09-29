<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\CrawlIncidentOccurred;
use App\Models\CrawlEvent;

final class PersistCrawlIncident
{
    public function handle(CrawlIncidentOccurred $event): void
    {
        CrawlEvent::query()->create([
            'source_slug' => $event->sourceSlug,
            'kind' => $event->kind,
            'url' => $event->url,
            'detail' => $event->detail,
            'created_at' => now(),
        ]);
    }
}
