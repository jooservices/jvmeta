<?php

declare(strict_types=1);

namespace App\Services\Crawl;

use App\Events\CrawlSourceUnhealthy;
use App\Models\Source;
use App\Observability\ObservabilityEmitter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;

final class SourceCircuitBreaker
{
    public function __construct(
        private readonly int $failureThreshold = 3,
        private readonly int $cooldownSeconds = 300,
    ) {}

    public function isAvailable(Source|string $source): bool
    {
        $model = $this->source($source);

        if ($model->circuit_state !== Source::CIRCUIT_OPEN) {
            return true;
        }

        $openedAt = $model->circuit_opened_at;
        if ($openedAt === null) {
            return false;
        }

        if (Carbon::parse($openedAt)->addSeconds($this->cooldownSeconds)->isFuture()) {
            return false;
        }

        $from = (string) $model->circuit_state;
        $model->forceFill(['circuit_state' => Source::CIRCUIT_HALF_OPEN])->save();
        $this->emitTransition($model->slug, $from, Source::CIRCUIT_HALF_OPEN, (int) $model->consecutive_failures);

        return true;
    }

    public function recordSuccess(Source|string $source): Source
    {
        $model = $this->source($source);
        $from = (string) $model->circuit_state;

        $model->forceFill([
            'consecutive_failures' => 0,
            'circuit_state' => Source::CIRCUIT_CLOSED,
            'circuit_opened_at' => null,
            'last_success_at' => now(),
            'last_error' => null,
        ])->save();

        $fresh = $model->refresh();
        $this->emitTransition($fresh->slug, $from, Source::CIRCUIT_CLOSED, 0);

        return $fresh;
    }

    public function recordFailure(Source|string $source, string $error): Source
    {
        $model = $this->source($source);
        $from = (string) $model->circuit_state;
        $failures = ((int) $model->consecutive_failures) + 1;
        $state = $model->circuit_state;
        $openedAt = $model->circuit_opened_at;

        if ($state === Source::CIRCUIT_HALF_OPEN || $failures >= $this->failureThreshold) {
            $state = Source::CIRCUIT_OPEN;
            $openedAt = now();
        }

        $model->forceFill([
            'consecutive_failures' => $failures,
            'circuit_state' => $state,
            'circuit_opened_at' => $openedAt,
            'last_error_at' => now(),
            'last_error' => $error,
        ])->save();

        $fresh = $model->refresh();
        $this->emitTransition($fresh->slug, $from, (string) $fresh->circuit_state, $failures);

        if ($fresh->circuit_state === Source::CIRCUIT_OPEN && $from !== Source::CIRCUIT_OPEN) {
            Event::dispatch(new CrawlSourceUnhealthy($fresh->slug, 'circuit_open', [
                'consecutive_failures' => $failures,
                'last_error' => $error,
            ]));
        }

        return $fresh;
    }

    private function emitTransition(string $sourceSlug, string $from, string $to, int $failures): void
    {
        app(ObservabilityEmitter::class)->emitCircuitTransition(
            $sourceSlug,
            $from,
            $to,
            $failures,
        );
    }

    private function source(Source|string $source): Source
    {
        if ($source instanceof Source) {
            return $source;
        }

        $model = Source::query()->find($source);
        if (! $model instanceof Source) {
            throw new InvalidArgumentException("Unknown source [{$source}].");
        }

        return $model;
    }
}
