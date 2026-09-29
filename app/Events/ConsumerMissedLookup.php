<?php

declare(strict_types=1);

namespace App\Events;

final class ConsumerMissedLookup
{
    /**
     * @param  'movie'|'performer'  $entity
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public readonly string $entity,
        public readonly string $query,
        public readonly array $context = [],
    ) {}
}
