<?php

declare(strict_types=1);

namespace App\Observability;

/**
 * Strips domain content and secrets before telemetry leave the process.
 */
final class TelemetrySanitizer
{
    /** @var list<string> */
    private const DENY_KEYS = [
        'url',
        'title',
        'title_jp',
        'title_en',
        'code',
        'code_normalized',
        'display_code',
        'value',
        'html',
        'body',
        'token',
        'password',
        'authorization',
        'api_key',
        'plaintext',
        'cover_url',
        'cover_thumb_url',
        'description',
        'name',
        'name_kanji',
        'name_kana',
        'draft',
        'movie',
        'performer',
        'performers',
        'aliases',
        'query',
        'payload',
    ];

    /** @var list<string> */
    private const ALLOW_DETAIL_KEYS = [
        'error_code',
        'reason',
        'enqueued',
        'parsed',
        'pages',
        'count',
        'failures',
        'attempts',
        'kind',
        'status',
        'source_slug',
        'outcome',
        'queue',
        'field',
        'tier',
        'queued',
        'gap_seconds',
        'items',
        'entity_type',
        'is_new',
        'sharp_drop',
        'drop',
        'completeness_tier',
        'from',
        'to',
        'duration_ms',
        'crawl_type',
        'ok',
        'entity',
        'dependency',
        'operation',
        'dispatched',
        'reclaimed',
        'tool',
        'action',
    ];

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function sanitizeContext(array $context): array
    {
        $clean = [];
        foreach ($context as $key => $value) {
            $normalized = strtolower((string) $key);
            if (in_array($normalized, self::DENY_KEYS, true)) {
                continue;
            }
            if (is_array($value)) {
                $nested = $this->sanitizeContext($value);
                if ($nested !== []) {
                    $clean[$key] = $nested;
                }

                continue;
            }
            if (is_scalar($value) || $value === null) {
                $clean[$key] = $value;
            }
        }

        return $clean;
    }

    /**
     * @param  array<string, mixed>  $detail
     * @return array<string, mixed>
     */
    public function sanitizeCrawlDetail(array $detail): array
    {
        $clean = [];
        foreach ($detail as $key => $value) {
            $normalized = strtolower((string) $key);
            if (! in_array($normalized, self::ALLOW_DETAIL_KEYS, true)) {
                continue;
            }
            if (is_int($value) || is_float($value) || is_bool($value) || is_string($value) || $value === null) {
                if (is_string($value) && $this->looksLikeDomainContent($value)) {
                    continue;
                }
                $clean[$key] = $value;
            }
        }

        return $clean;
    }

    public function looksLikeDomainContent(string $value): bool
    {
        if (preg_match('/\b[A-Z]{2,10}-?\d{2,7}\b/', $value) === 1) {
            return true;
        }

        return strlen($value) > 120;
    }
}
