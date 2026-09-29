<?php

declare(strict_types=1);

namespace App\Services\Merge;

use App\Models\MovieObservation;
use Illuminate\Support\Collection;

/**
 * Strategy that picks the primary observation for a field from the recorded
 * observations of that field (BR-2). Implementations are swap-able without
 * touching the merge/persist code (REQ-D4).
 */
interface ConflictPolicy
{
    /**
     * @param  Collection<int, MovieObservation>  $observations
     */
    public function pick(string $field, Collection $observations): ?MovieObservation;
}
