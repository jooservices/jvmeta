<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Http\Resources\MovieResource;
use App\Http\Resources\PerformerResource;
use App\Models\Performer;
use App\Models\Movie;
use App\Services\Auth\ApiKeyService;
use App\Services\Miss\ConsumerMissReporter;
use App\Services\Movies\MovieLookupService;
use App\Services\Search\MovieSearchService;
use App\Support\Code\NormalizedCode;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Throwable;

/**
 * Minimal MCP stdio server (JSON-RPC 2.0) for AI integration.
 * Tools: lookup_movie, search_movies, get_performer.
 * Auth: JVMETA_MCP_API_KEY must be an active API key (same store as HTTP).
 */
final class McpServeCommand extends Command
{
    protected $signature = 'mcp:serve';

    protected $description = 'Serve JVMeta MCP tools over stdin/stdout (JSON-RPC).';

    public function handle(
        MovieLookupService $lookup,
        MovieSearchService $search,
        ApiKeyService $apiKeys,
        ConsumerMissReporter $misses,
    ): int {
        if (! $this->authenticate($apiKeys)) {
            $this->error('MCP auth failed: set JVMETA_MCP_API_KEY to an active jvm_… API key.');

            return self::FAILURE;
        }

        $stdin = fopen('php://stdin', 'r');
        if ($stdin === false) {
            $this->error('Unable to open stdin.');

            return self::FAILURE;
        }

        while (($line = fgets($stdin)) !== false) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            try {
                /** @var array<string, mixed> $message */
                $message = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
            } catch (Throwable) {
                $this->writeRpcError(null, -32700, 'Parse error');

                continue;
            }

            $id = $message['id'] ?? null;
            $method = $message['method'] ?? null;

            if (! is_string($method)) {
                $this->writeRpcError($id, -32600, 'Invalid Request');

                continue;
            }

            try {
                $result = match ($method) {
                    'initialize' => [
                        'protocolVersion' => '2024-11-05',
                        'capabilities' => ['tools' => (object) []],
                        'serverInfo' => ['name' => 'jvmeta', 'version' => '0.1.0'],
                    ],
                    'notifications/initialized', 'initialized' => null,
                    'tools/list' => ['tools' => $this->toolDefinitions()],
                    'tools/call' => $this->callTool(
                        is_array($message['params'] ?? null) ? $message['params'] : [],
                        $lookup,
                        $search,
                        $misses,
                        $apiKeys,
                    ),
                    'ping' => (object) [],
                    default => throw new InvalidArgumentException("Method not found: {$method}"),
                };
            } catch (Throwable $e) {
                if ($method === 'notifications/initialized' || $method === 'initialized') {
                    continue;
                }
                $this->writeRpcError($id, -32601, $e->getMessage());

                continue;
            }

            if ($result === null || ! array_key_exists('id', $message)) {
                continue;
            }

            $this->writeRpc(['jsonrpc' => '2.0', 'id' => $id, 'result' => $result]);
        }

        return self::SUCCESS;
    }

    private function authenticate(ApiKeyService $apiKeys): bool
    {
        $plain = (string) config('jvmeta_auth.mcp_api_key', '');
        if ($plain === '') {
            return false;
        }

        return $apiKeys->findActiveByPlaintext($plain) !== null;
    }

    /** @return list<array<string, mixed>> */
    private function toolDefinitions(): array
    {
        return [
            [
                'name' => 'lookup_movie',
                'description' => 'Lookup one normalized movie by code (Postgres SoR).',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'code' => ['type' => 'string', 'description' => 'DVD/amateur code e.g. STARS-456'],
                    ],
                    'required' => ['code'],
                ],
            ],
            [
                'name' => 'search_movies',
                'description' => 'Search movies via Elasticsearch then hydrate from Postgres.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'q' => ['type' => 'string'],
                        'per_page' => ['type' => 'integer', 'default' => 10],
                    ],
                    'required' => ['q'],
                ],
            ],
            [
                'name' => 'get_performer',
                'description' => 'Get a performer by numeric id or uuid from Postgres.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'string', 'description' => 'Performer id or uuid'],
                    ],
                    'required' => ['id'],
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array{content: list<array{type: string, text: string}>, isError?: bool}
     */
    private function callTool(
        array $params,
        MovieLookupService $lookup,
        MovieSearchService $search,
        ConsumerMissReporter $misses,
        ApiKeyService $apiKeys,
    ): array {
        if (! $this->authenticate($apiKeys)) {
            return [
                'content' => [['type' => 'text', 'text' => json_encode(['error' => 'unauthorized'], JSON_UNESCAPED_UNICODE) ?: '{}']],
                'isError' => true,
            ];
        }

        $name = $params['name'] ?? null;
        $arguments = is_array($params['arguments'] ?? null) ? $params['arguments'] : [];

        if (is_string($name) && $name !== '') {
            app(\App\Observability\ObservabilityEmitter::class)->emitOps('mcp_tool', ['tool' => $name]);
        }

        $payload = match ($name) {
            'lookup_movie' => $this->toolLookupMovie((string) ($arguments['code'] ?? ''), $lookup, $misses),
            'search_movies' => $this->toolSearchMovies(
                (string) ($arguments['q'] ?? ''),
                (int) ($arguments['per_page'] ?? 10),
                $search,
            ),
            'get_performer' => $this->toolGetPerformer((string) ($arguments['id'] ?? ''), $misses),
            default => ['error' => 'Unknown tool'],
        };

        return [
            'content' => [[
                'type' => 'text',
                'text' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: '{}',
            ]],
            'isError' => isset($payload['error']),
        ];
    }

    /** @return array<string, mixed> */
    private function toolLookupMovie(string $code, MovieLookupService $lookup, ConsumerMissReporter $misses): array
    {
        if ($code === '') {
            return ['error' => 'code required'];
        }

        try {
            NormalizedCode::from($code);
        } catch (InvalidArgumentException $e) {
            return ['error' => $e->getMessage()];
        }

        $movie = $lookup->findByCode($code);
        if ($movie === null) {
            $misses->report('movie', $code, ['endpoint' => 'mcp.lookup_movie']);

            return ['error' => 'movie_not_found', 'code' => $code];
        }

        return (new MovieResource($movie))->toArray(Request::create('/'));
    }

    /** @return array<string, mixed> */
    private function toolSearchMovies(string $q, int $perPage, MovieSearchService $search): array
    {
        if (trim($q) === '') {
            return ['error' => 'q required'];
        }

        $result = $search->search(['q' => $q], 'relevance', null, max(1, min(50, $perPage)));

        return [
            'total' => $result['total'],
            'items' => $result['items']->map(static fn(Movie $w): array => [
                'uuid' => $w->uuid,
                'code' => $w->display_code,
                'title_jp' => $w->title_jp,
                'title_en' => $w->title_en,
                'cover_url' => $w->cover_url,
            ])->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function toolGetPerformer(string $id, ConsumerMissReporter $misses): array
    {
        if ($id === '') {
            return ['error' => 'id required'];
        }

        $query = Performer::query()->with('aliases')->withCount('movies');
        $performer = ctype_digit($id)
            ? $query->whereKey((int) $id)->first()
            : $query->where('uuid', $id)->first();

        if (! $performer instanceof Performer) {
            $misses->report('performer', $id, ['endpoint' => 'mcp.get_performer']);

            return ['error' => 'performer_not_found'];
        }

        return (new PerformerResource($performer))->toArray(Request::create('/'));
    }

    /** @param array<string, mixed> $payload */
    private function writeRpc(array $payload): void
    {
        fwrite(STDOUT, json_encode($payload, JSON_UNESCAPED_UNICODE) . "\n");
    }

    private function writeRpcError(mixed $id, int $code, string $message): void
    {
        $this->writeRpc([
            'jsonrpc' => '2.0',
            'id' => $id,
            'error' => ['code' => $code, 'message' => $message],
        ]);
    }
}
