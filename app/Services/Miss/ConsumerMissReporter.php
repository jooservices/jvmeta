<?php

declare(strict_types=1);

namespace App\Services\Miss;

use App\Events\ConsumerMissedLookup;
use Illuminate\Support\Facades\Event;

final class ConsumerMissReporter
{
    /**
     * @param  'movie'|'performer'  $entity
     * @param  array<string, mixed>  $context
     */
    public function report(string $entity, string $query, array $context = []): void
    {
        $trimmed = trim($query);
        if ($trimmed === '') {
            return;
        }

        Event::dispatch(new ConsumerMissedLookup($entity, $trimmed, $context));
    }
}
