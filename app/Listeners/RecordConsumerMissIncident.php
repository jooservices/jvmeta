<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\ConsumerMissedLookup;
use App\Events\CrawlIncidentOccurred;
use Illuminate\Support\Facades\Event;

/**
 * Turns a consumer miss into a crawl incident (Postgres + OpenObserve via listeners).
 */
final class RecordConsumerMissIncident
{
    public function handle(ConsumerMissedLookup $event): void
    {
        Event::dispatch(new CrawlIncidentOccurred(
            sourceSlug: '_api',
            kind: 'consumer_miss',
            url: null,
            detail: [
                'entity' => $event->entity,
                'query' => $event->query,
                ...$event->context,
            ],
        ));
    }
}
