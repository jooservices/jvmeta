<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\PerformerNotFoundException;
use App\Http\Requests\PerformerSearchRequest;
use App\Http\Resources\PerformerResource;
use App\Http\Resources\PerformerSummaryResource;
use App\Models\Performer;
use App\Repositories\PerformerRepository;
use App\Services\Miss\ConsumerMissReporter;
use Illuminate\Http\JsonResponse;
use JOOservices\LaravelController\Http\Controllers\BaseApiController;

final class PerformerController extends BaseApiController
{
    public function index(PerformerSearchRequest $request, PerformerRepository $performers): JsonResponse
    {
        $perPage = (int) $request->validated('per_page', 15);

        $repository = $performers->withDisplayRelations()->orderBy(['id' => 'asc']);

        $q = $request->validated('q');
        if (is_string($q)) {
            $repository = $repository->search($q);
        }

        return $this->respondWithPagination(
            paginator: $repository->paginate($perPage),
            resourceClass: PerformerSummaryResource::class,
            message: 'Performers retrieved successfully.',
        );
    }

    public function show(PerformerRepository $performers, ConsumerMissReporter $misses, int $id): JsonResponse
    {
        $performer = $performers->findForDisplay($id);

        if (! $performer instanceof Performer) {
            $misses->report('performer', (string) $id, ['endpoint' => 'performers.show']);

            throw new PerformerNotFoundException($id);
        }

        return $this->respondWithResource(
            resource: new PerformerResource($performer),
            message: 'Performer retrieved successfully.',
        );
    }
}
