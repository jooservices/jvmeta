<?php

declare(strict_types=1);

namespace App\Observability;

/**
 * Sync fail-open ingest to OpenObserve (no queue — workers do not drain `default`).
 */
final class ObservabilityEmitter
{
    public function __construct(
        private readonly TelemetrySanitizer $sanitizer,
        private readonly OpenObserveClient $client,
    ) {}

    /**
     * @param  array<string, mixed>  $detail
     */
    public function emitCrawlEvent(string $sourceSlug, string $kind, array $detail = []): void
    {
        $this->emit([
            'event' => 'crawl_event',
            'source_slug' => $sourceSlug,
            'kind' => $kind,
            'detail' => $this->sanitizer->sanitizeCrawlDetail($detail),
        ]);
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    public function emitApiUsage(array $fields): void
    {
        $this->emit([
            'event' => 'api_usage',
            ...$this->sanitizer->sanitizeContext($fields),
        ]);
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    public function emitPersist(string $entity, array $fields): void
    {
        $this->emit([
            'event' => 'persist',
            'entity' => $entity,
            ...$this->sanitizer->sanitizeContext($fields),
        ]);
    }

    public function emitCircuitTransition(string $sourceSlug, string $from, string $to, int $consecutiveFailures = 0): void
    {
        if ($from === $to) {
            return;
        }

        $this->emit([
            'event' => 'circuit_transition',
            'source_slug' => $sourceSlug,
            'from' => $from,
            'to' => $to,
            'consecutive_failures' => $consecutiveFailures,
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $attempts  crawlerx fetch attempts (method, elapsed_ms, status, challenge, ok)
     */
    public function emitFetch(string $sourceSlug, string $crawlType, bool $ok, int $durationMs, ?string $errorCode = null, array $attempts = []): void
    {
        $this->emit([
            'event' => 'crawl_fetch',
            'source_slug' => $sourceSlug,
            'crawl_type' => $crawlType,
            'ok' => $ok,
            'duration_ms' => $durationMs,
            'error_code' => $errorCode,
            'attempt_count' => count($attempts),
            'attempts' => array_map(static fn(array $attempt): array => [
                'method' => is_string($attempt['method'] ?? null) ? $attempt['method'] : null,
                'elapsed_ms' => is_int($attempt['elapsed_ms'] ?? null) ? $attempt['elapsed_ms'] : null,
                'status' => is_int($attempt['status'] ?? null) ? $attempt['status'] : null,
                'challenge' => (bool) ($attempt['challenge'] ?? false),
                'ok' => (bool) ($attempt['ok'] ?? false),
            ], $attempts),
        ]);
    }

    public function emitDependency(string $dependency, string $operation, bool $ok, int $durationMs = 0): void
    {
        $this->emit([
            'event' => 'dependency',
            'dependency' => $dependency,
            'operation' => $operation,
            'ok' => $ok,
            'duration_ms' => $durationMs,
        ]);
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    public function emitOps(string $kind, array $fields = []): void
    {
        $this->emit([
            'event' => 'ops',
            'kind' => $kind,
            ...$this->sanitizer->sanitizeContext($fields),
        ]);
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function emit(array $record): void
    {
        $this->client->ingestLogs([[
            '_timestamp' => (int) (microtime(true) * 1_000_000),
            ...$this->sanitizer->sanitizeContext($record),
        ]]);
    }
}
