<?php

declare(strict_types=1);

namespace App\Events;

/**
 * Domain/ops fact: something noteworthy happened during crawl or consumer miss.
 * Listeners persist to Postgres and mirror sanitized detail to OpenObserve.
 */
final class CrawlIncidentOccurred
{
    /**
     * @param  array<string, mixed>  $detail
     */
    public function __construct(
        public readonly string $sourceSlug,
        public readonly string $kind,
        public readonly ?string $url = null,
        public readonly array $detail = [],
    ) {}
}
