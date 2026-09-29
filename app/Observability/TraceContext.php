<?php

declare(strict_types=1);

namespace App\Observability;

final class TraceContext
{
    public static function newTraceId(): string
    {
        return bin2hex(random_bytes(16));
    }

    public static function newSpanId(): string
    {
        return bin2hex(random_bytes(8));
    }

    /**
     * W3C traceparent: version-traceid-spanid-flags
     */
    public static function formatTraceparent(string $traceId, string $spanId, string $flags = '01'): string
    {
        return sprintf('00-%s-%s-%s', $traceId, $spanId, $flags);
    }

    /**
     * @return array{trace_id: string, span_id: string, flags: string}|null
     */
    public static function parseTraceparent(?string $header): ?array
    {
        if ($header === null || $header === '') {
            return null;
        }

        $parts = explode('-', trim($header));
        if (count($parts) !== 4 || strlen($parts[1]) !== 32 || strlen($parts[2]) !== 16) {
            return null;
        }

        return [
            'trace_id' => $parts[1],
            'span_id' => $parts[2],
            'flags' => $parts[3],
        ];
    }
}
