<?php

declare(strict_types=1);

namespace App\Services\Mcp;

use App\Exceptions\InvalidFilterException;
use App\Http\Resources\MovieResource;
use App\Http\Resources\MovieSummaryResource;
use App\Http\Resources\PerformerResource;
use App\Http\Resources\PerformerSummaryResource;
use App\Models\Movie;
use App\Models\Performer;
use App\Repositories\PerformerRepository;
use App\Services\Crawl\CrawlStatusService;
use App\Services\Health\SystemStatusService;
use App\Services\Miss\ConsumerMissReporter;
use App\Services\Movies\MovieLookupService;
use App\Services\Search\CursorCodec;
use App\Services\Search\MovieSearchService;
use App\Support\Code\NormalizedCode;
use Illuminate\Http\Request;
use InvalidArgumentException;

final class McpToolService
{
    public function __construct(
        private readonly MovieLookupService $movieLookup,
        private readonly MovieSearchService $movieSearch,
        private readonly PerformerRepository $performers,
        private readonly CursorCodec $cursorCodec,
        private readonly ConsumerMissReporter $misses,
        private readonly CrawlStatusService $crawlStatusService,
        private readonly SystemStatusService $systemStatusService,
    ) {}

    /** @return list<array<string, mixed>> */
    public function definitions(): array
    {
        return [
            [
                'name' => 'lookup_movies',
                'description' => 'Return a filtered, sorted and cursor-paginated list of movies.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'q' => ['type' => 'string', 'description' => 'Keyword across code, title and performer names.'],
                        'code' => ['type' => 'string', 'description' => 'Movie code or partial code.'],
                        'genre' => ['type' => 'string'],
                        'actress' => ['type' => 'string'],
                        'maker' => ['type' => 'string'],
                        'series' => ['type' => 'string'],
                        'label' => ['type' => 'string'],
                        'released_from' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
                        'released_to' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
                        'runtime_min' => ['type' => 'integer'],
                        'runtime_max' => ['type' => 'integer'],
                        'censored' => [
                            'oneOf' => [
                                ['type' => 'boolean'],
                                ['type' => 'integer', 'enum' => [0, 1]],
                                ['type' => 'string', 'enum' => ['0', '1', 'censored', 'uncensored']],
                            ],
                        ],
                        'sort' => ['type' => 'string', 'enum' => CursorCodec::SORTS, 'default' => 'relevance'],
                        'cursor' => ['type' => 'string'],
                        'per_page' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 10],
                        'count_only' => ['type' => 'boolean', 'description' => 'Return only the total count instead of the item list.'],
                    ],
                ],
            ],
            [
                'name' => 'get_movie',
                'description' => 'Return one complete movie by normalized code, including media URL references.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'code' => ['type' => 'string', 'description' => 'DVD/amateur code, for example STARS-456.'],
                    ],
                    'required' => ['code'],
                ],
            ],
            [
                'name' => 'search',
                'description' => 'Semantic (natural-language) movie search. Understands meaning, not just keywords.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'q' => ['type' => 'string', 'description' => 'Natural-language description, e.g. "cheeky schoolgirl part-time at a tavern".'],
                        'per_page' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 10],
                    ],
                    'required' => ['q'],
                ],
            ],
            [
                'name' => 'lookup_performers',
                'description' => 'Return a filtered, sorted and cursor-paginated list of performers.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'q' => ['type' => 'string', 'description' => 'Name, alias or bio text.'],
                        'age_min' => ['type' => 'integer'],
                        'age_max' => ['type' => 'integer'],
                        'height_min' => ['type' => 'integer'],
                        'height_max' => ['type' => 'integer'],
                        'bust_min' => ['type' => 'integer'],
                        'bust_max' => ['type' => 'integer'],
                        'waist_min' => ['type' => 'integer'],
                        'waist_max' => ['type' => 'integer'],
                        'hip_min' => ['type' => 'integer'],
                        'hip_max' => ['type' => 'integer'],
                        'cup' => ['type' => 'string'],
                        'blood_type' => ['type' => 'string'],
                        'location' => ['type' => 'string'],
                        'sort' => ['type' => 'string', 'enum' => PerformerRepository::SORTS, 'default' => 'name'],
                        'cursor' => ['type' => 'string'],
                        'per_page' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 10],
                        'count_only' => ['type' => 'boolean', 'description' => 'Return only the total count instead of the item list.'],
                    ],
                ],
            ],
            [
                'name' => 'get_counts',
                'description' => 'Return the total number of movies and performers in the catalog.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [],
                ],
            ],
            [
                'name' => 'crawl_status',
                'description' => 'Return crawl pipeline status: catalog counts, queue depth, per-source health and per-instance worker liveness.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [],
                ],
            ],
            [
                'name' => 'system_status',
                'description' => 'Return health of the backing services: database, Elasticsearch, embedder, activity log and observability.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [],
                ],
            ],
            [
                'name' => 'get_performer',
                'description' => 'Return one complete performer profile by numeric id or UUID.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'string', 'description' => 'Performer numeric id or UUID.'],
                    ],
                    'required' => ['id'],
                ],
            ],
        ];
    }

    /** @param array<string, mixed> $arguments */
    /** @return array<string, mixed> */
    public function call(string $name, array $arguments): array
    {
        try {
            return match ($name) {
                'lookup_movies' => $this->lookupMovies($arguments),
                'get_movie' => $this->getMovie($arguments),
                'search' => $this->search($arguments),
                'lookup_performers' => $this->lookupPerformers($arguments),
                'get_performer' => $this->getPerformer($arguments),
                'get_counts' => $this->getCounts($arguments),
                'crawl_status' => $this->crawlStatus(),
                'system_status' => $this->systemStatus(),
                default => ['error' => 'unknown_tool'],
            };
        } catch (InvalidFilterException $exception) {
            return ['error' => 'invalid_filter', 'message' => $exception->getMessage()];
        }
    }

    /** @param array<string, mixed> $arguments */
    /** @return array<string, mixed> */
    private function lookupMovies(array $arguments): array
    {
        $filters = $this->movieFilters($arguments);
        $sort = $this->stringArgument($arguments, 'sort') ?? 'relevance';
        $this->assertAllowedSort($sort, CursorCodec::SORTS);

        if ($this->booleanArgument($arguments, 'count_only')) {
            return ['count' => $this->movieSearch->count($filters)];
        }

        $perPage = $this->perPage($arguments);
        $cursorValue = $this->stringArgument($arguments, 'cursor');
        $cursor = $cursorValue !== null ? $this->cursorCodec->decode($cursorValue, CursorCodec::SORTS) : null;

        if ($cursor !== null && $cursor['sort'] !== $sort) {
            throw new InvalidFilterException('The sort parameter cannot change between pages.');
        }

        $result = $this->movieSearch->search($filters, $cursor['sort'] ?? $sort, $cursor, $perPage);
        $nextCursor = $result['last_id'] === null
            ? null
            : $this->cursorCodec->encode($result['sort'], $result['last_sort'], $result['last_id'], CursorCodec::SORTS);
        $request = Request::create('/');

        return [
            'items' => $result['items']->map(fn(Movie $movie): array => (new MovieSummaryResource($movie))->toArray($request))->values()->all(),
            'pagination' => [
                'total' => $result['total'],
                'per_page' => $perPage,
                'sort' => $result['sort'],
                'has_more' => $result['has_more'],
                'cursor' => $cursorValue,
                'next_cursor' => $nextCursor,
            ],
        ];
    }

    /** @param array<string, mixed> $arguments */
    /** @return array<string, mixed> */
    private function getMovie(array $arguments): array
    {
        $code = $this->requiredStringArgument($arguments, 'code');

        try {
            NormalizedCode::from($code);
        } catch (InvalidArgumentException $exception) {
            return ['error' => 'invalid_code', 'message' => $exception->getMessage()];
        }

        $movie = $this->movieLookup->findByCode($code);
        if (! $movie instanceof Movie) {
            $this->misses->report('movie', $code, ['endpoint' => 'mcp.get_movie']);

            return ['error' => 'movie_not_found', 'code' => $code];
        }

        return (new MovieResource($movie))->toArray(Request::create('/'));
    }

    /** @param array<string, mixed> $arguments */
    /** @return array<string, mixed> */
    private function search(array $arguments): array
    {
        $q = $this->requiredStringArgument($arguments, 'q');
        $perPage = $this->perPage($arguments);

        $result = $this->movieSearch->semanticSearch($q, $perPage);
        $request = Request::create('/');

        return [
            'query' => $q,
            'items' => $result['items']->map(fn(Movie $movie): array => (new MovieSummaryResource($movie))->toArray($request))->values()->all(),
            'pagination' => [
                'total' => $result['total'],
                'per_page' => $perPage,
                'has_more' => $result['has_more'],
            ],
        ];
    }

    /** @param array<string, mixed> $arguments */
    /** @return array<string, mixed> */
    private function lookupPerformers(array $arguments): array
    {
        $filters = $this->performerFilters($arguments);
        $sort = $this->stringArgument($arguments, 'sort') ?? 'name';
        $this->assertAllowedSort($sort, PerformerRepository::SORTS);

        if ($this->booleanArgument($arguments, 'count_only')) {
            return ['count' => $this->performers->countFiltered($filters)];
        }

        $perPage = $this->perPage($arguments);
        $cursorValue = $this->stringArgument($arguments, 'cursor');
        $cursor = $cursorValue !== null
            ? $this->cursorCodec->decode($cursorValue, PerformerRepository::SORTS)
            : null;

        if ($cursor !== null && $cursor['sort'] !== $sort) {
            throw new InvalidFilterException('The sort parameter cannot change between pages.');
        }

        $result = $this->performers->search($filters, $cursor['sort'] ?? $sort, $cursor, $perPage);
        $nextCursor = $result['last_id'] === null
            ? null
            : $this->cursorCodec->encode($result['sort'], $result['last_sort'], $result['last_id'], PerformerRepository::SORTS);
        $request = Request::create('/');

        return [
            'items' => $result['items']->map(fn(Performer $performer): array => (new PerformerSummaryResource($performer))->toArray($request))->values()->all(),
            'pagination' => [
                'total' => $result['total'],
                'per_page' => $perPage,
                'sort' => $result['sort'],
                'has_more' => $result['has_more'],
                'cursor' => $cursorValue,
                'next_cursor' => $nextCursor,
            ],
        ];
    }

    /** @param array<string, mixed> $arguments */
    /** @return array<string, mixed> */
    private function getPerformer(array $arguments): array
    {
        $id = $this->requiredStringArgument($arguments, 'id');
        $performer = $this->performers->findForDisplay($id);

        if (! $performer instanceof Performer) {
            $this->misses->report('performer', $id, ['endpoint' => 'mcp.get_performer']);

            return ['error' => 'performer_not_found', 'id' => $id];
        }

        return (new PerformerResource($performer))->toArray(Request::create('/'));
    }

    /** @return array<string, mixed> */
    private function getCounts(array $arguments): array
    {
        unset($arguments);

        return [
            'movies' => (int) Movie::query()->count(),
            'performers' => (int) Performer::query()->count(),
        ];
    }

    /** @return array<string, mixed> */
    private function crawlStatus(): array
    {
        return $this->crawlStatusService->status();
    }

    /** @return array<string, mixed> */
    private function systemStatus(): array
    {
        return $this->systemStatusService->status();
    }

    /** @param array<string, mixed> $arguments */
    private function booleanArgument(array $arguments, string $key): bool
    {
        $value = $arguments[$key] ?? false;

        if (is_bool($value)) {
            return $value;
        }

        if ($value === 1 || $value === '1' || $value === 'true') {
            return true;
        }

        return false;
    }

    /** @param array<string, mixed> $arguments */
    /** @return array<string, mixed> */
    private function movieFilters(array $arguments): array
    {
        $keys = [
            'q', 'code', 'genre', 'actress', 'maker', 'series', 'label',
            'released_from', 'released_to', 'runtime_min', 'runtime_max', 'censored',
        ];

        $filters = [];
        foreach ($keys as $key) {
            if (array_key_exists($key, $arguments)) {
                $filters[$key] = $arguments[$key];
            }
        }

        if (isset($filters['censored'])) {
            $filters['censored'] = $this->censoredValue($filters['censored']);
        }

        return $filters;
    }

    /** @param array<string, mixed> $arguments */
    /** @return array<string, mixed> */
    private function performerFilters(array $arguments): array
    {
        $keys = [
            'q', 'age_min', 'age_max', 'height_min', 'height_max', 'bust_min', 'bust_max',
            'waist_min', 'waist_max', 'hip_min', 'hip_max', 'cup', 'blood_type', 'location',
        ];

        $filters = [];
        foreach ($keys as $key) {
            if (array_key_exists($key, $arguments)) {
                $filters[$key] = $arguments[$key];
            }
        }

        return $filters;
    }

    private function censoredValue(mixed $value): int
    {
        if ($value === true || $value === 1 || $value === '1' || $value === 'censored') {
            return 1;
        }

        if ($value === false || $value === 0 || $value === '0' || $value === 'uncensored') {
            return 0;
        }

        throw new InvalidFilterException('censored must be censored, uncensored, 0 or 1.');
    }

    /** @param list<string> $allowed */
    private function assertAllowedSort(string $sort, array $allowed): void
    {
        if (! in_array($sort, $allowed, true)) {
            throw new InvalidFilterException('Unsupported sort.');
        }
    }

    /** @param array<string, mixed> $arguments */
    private function perPage(array $arguments): int
    {
        $value = $arguments['per_page'] ?? 10;
        if (! is_int($value) && ! (is_string($value) && ctype_digit($value))) {
            throw new InvalidFilterException('per_page must be an integer.');
        }

        $perPage = (int) $value;
        if ($perPage < 1 || $perPage > 100) {
            throw new InvalidFilterException('per_page must be between 1 and 100.');
        }

        return $perPage;
    }

    /** @param array<string, mixed> $arguments */
    private function requiredStringArgument(array $arguments, string $key): string
    {
        $value = $this->stringArgument($arguments, $key);
        if ($value === null) {
            throw new InvalidFilterException("{$key} is required.");
        }

        return $value;
    }

    /** @param array<string, mixed> $arguments */
    private function stringArgument(array $arguments, string $key): ?string
    {
        $value = $arguments[$key] ?? null;

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
