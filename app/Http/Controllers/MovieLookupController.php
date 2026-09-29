<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\MovieNotFoundException;
use App\Http\Resources\MovieResource;
use App\Models\Movie;
use App\Services\Miss\ConsumerMissReporter;
use App\Services\Movies\MovieLookupService;
use Illuminate\Http\JsonResponse;
use JOOservices\LaravelController\Http\Controllers\BaseApiController;

final class MovieLookupController extends BaseApiController
{
    public function show(string $code, MovieLookupService $movies, ConsumerMissReporter $misses): JsonResponse
    {
        $movie = $movies->findByCode($code);

        if (! $movie instanceof Movie) {
            $misses->report('movie', $code, ['endpoint' => 'movies.show']);

            throw new MovieNotFoundException($code);
        }

        return $this->respondWithResource(
            resource: new MovieResource($movie),
            message: 'Movie retrieved successfully.',
        );
    }
}
