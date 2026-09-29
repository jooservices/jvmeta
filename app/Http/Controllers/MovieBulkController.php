<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\MovieBulkRequest;
use App\Http\Resources\MovieSummaryResource;
use App\Models\Movie;
use App\Services\Movies\MovieLookupService;
use Illuminate\Http\JsonResponse;
use JOOservices\LaravelController\Http\Controllers\BaseApiController;

final class MovieBulkController extends BaseApiController
{
    public function store(MovieBulkRequest $request, MovieLookupService $movies): JsonResponse
    {
        $foundByCode = $movies->findByCodes($request->codes());

        $results = [];
        foreach ($request->codes() as $rawCode) {
            $normalized = $movies->normalize($rawCode);
            $key = $normalized?->value() ?? trim($rawCode);

            if (isset($results[$key])) {
                continue;
            }

            $movie = $normalized !== null ? ($foundByCode[$normalized->value()] ?? null) : null;

            $results[$key] = [
                'code' => $normalized?->display() ?? trim($rawCode),
                'status' => $movie instanceof Movie ? 'found' : 'not_found',
                'movie' => $movie instanceof Movie
                    ? (new MovieSummaryResource($movie))->resolve($request)
                    : null,
            ];
        }

        return $this->respondWithData(
            data: array_values($results),
            message: 'Bulk movie lookup completed.',
        );
    }
}
