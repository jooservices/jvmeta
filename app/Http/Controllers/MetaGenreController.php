<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Genre;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use JOOservices\LaravelController\Http\Controllers\BaseApiController;

final class MetaGenreController extends BaseApiController
{
    /**
     * Union of genre labels across sources (AC-1.6, D-24): no canonical
     * taxonomy — the raw label when present, the normalized label otherwise,
     * deduplicated and sorted for consumer filter UIs.
     */
    public function index(): JsonResponse
    {
        $labels = Genre::query()
            ->distinct()
            ->pluck(DB::raw('COALESCE(label_raw, label_normalized)'))
            ->sort()
            ->values()
            ->all();

        return $this->respondWithData(
            data: $labels,
            message: 'Genres retrieved successfully.',
        );
    }
}
