<?php

declare(strict_types=1);

namespace App\Services\Crawl;

use App\Models\Source;
use InvalidArgumentException;

final class SourceThrottle
{
    public function currentGap(Source|string $source): float
    {
        $model = $this->source($source);

        return (float) ($model->gap_seconds_current ?? $model->gap_seconds_default);
    }

    public function onSuccess(Source|string $source): Source
    {
        $model = $this->source($source);
        $minimum = (float) ($model->gap_seconds_min ?? $model->gap_seconds_default);
        $current = $this->currentGap($model);
        $step = max(0.5, $minimum * 0.10);

        $model->forceFill([
            'gap_seconds_current' => max($minimum, $current - $step),
        ])->save();

        return $model->refresh();
    }

    public function onFailure(Source|string $source): Source
    {
        $model = $this->source($source);
        $maximum = (float) ($model->gap_seconds_max ?? $model->gap_seconds_default);
        $current = $this->currentGap($model);

        $model->forceFill([
            'gap_seconds_current' => min($maximum, max($current, 1.0) * 2),
        ])->save();

        return $model->refresh();
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
