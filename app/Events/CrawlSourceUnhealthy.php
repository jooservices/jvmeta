<?php

declare(strict_types=1);

namespace App\Events;

final class CrawlSourceUnhealthy
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public readonly string $sourceSlug,
        public readonly string $reason,
        public readonly array $context = [],
    ) {}
}
