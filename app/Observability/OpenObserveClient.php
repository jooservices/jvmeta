<?php

declare(strict_types=1);

namespace App\Observability;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Fail-open ingest to OpenObserve via JSON logs + OTLP HTTP (no SDK).
 */
final class OpenObserveClient
{
    public function __construct(
        private readonly TelemetrySanitizer $sanitizer,
    ) {}

    public function enabled(): bool
    {
        return (bool) config('openobserve.enabled', false);
    }

    /**
     * @param  list<array<string, mixed>>  $records
     */
    public function ingestLogs(array $records): void
    {
        if (! $this->enabled() || $records === []) {
            return;
        }

        $stream = (string) config('openobserve.stream_logs');
        $payload = [];
        foreach ($records as $record) {
            $payload[] = array_merge(
                [
                    '_timestamp' => (int) ((float) ($record['_timestamp'] ?? (microtime(true) * 1_000_000))),
                    'service' => (string) config('openobserve.service_name'),
                ],
                $this->sanitizer->sanitizeContext($record),
            );
        }

        $this->postJson($this->jsonIngestUrl($stream), $payload);
    }

    /**
     * @param  list<array{name: string, value: float|int, labels?: array<string, string>}>  $points
     */
    public function ingestMetrics(array $points): void
    {
        if (! $this->enabled() || $points === []) {
            return;
        }

        $nowNs = (string) (int) (microtime(true) * 1_000_000_000);
        $scopeMetrics = [];
        foreach ($points as $point) {
            $labels = $point['labels'] ?? [];
            $attributes = [];
            foreach ($labels as $key => $value) {
                $attributes[] = [
                    'key' => (string) $key,
                    'value' => ['stringValue' => (string) $value],
                ];
            }
            $scopeMetrics[] = [
                'name' => $point['name'],
                'gauge' => [
                    'dataPoints' => [[
                        'asDouble' => (float) $point['value'],
                        'timeUnixNano' => $nowNs,
                        'attributes' => $attributes,
                    ]],
                ],
            ];
        }

        $body = [
            'resourceMetrics' => [[
                'resource' => [
                    'attributes' => [[
                        'key' => 'service.name',
                        'value' => ['stringValue' => (string) config('openobserve.service_name')],
                    ]],
                ],
                'scopeMetrics' => [[
                    'metrics' => $scopeMetrics,
                ]],
            ]],
        ];

        $this->postJson($this->otlpUrl('metrics'), $body, 'application/json');
    }

    /**
     * @param  array{
     *     name: string,
     *     trace_id: string,
     *     span_id: string,
     *     parent_span_id?: string|null,
     *     start_ns: int,
     *     end_ns: int,
     *     attributes?: array<string, scalar|null>,
     *     status_code?: int
     * }  $span
     */
    public function ingestSpan(array $span): void
    {
        if (! $this->enabled()) {
            return;
        }

        if (! $this->shouldSample()) {
            return;
        }

        $attributes = [];
        foreach ($this->sanitizer->sanitizeContext($span['attributes'] ?? []) as $key => $value) {
            if ($value === null) {
                continue;
            }
            $attributes[] = [
                'key' => (string) $key,
                'value' => is_bool($value) || is_int($value) || is_float($value)
                    ? ['stringValue' => (string) $value]
                    : ['stringValue' => (string) $value],
            ];
        }

        $otlpSpan = [
            'traceId' => $span['trace_id'],
            'spanId' => $span['span_id'],
            'name' => $span['name'],
            'kind' => 1,
            'startTimeUnixNano' => (string) $span['start_ns'],
            'endTimeUnixNano' => (string) $span['end_ns'],
            'attributes' => $attributes,
            'status' => [
                'code' => ($span['status_code'] ?? 0) >= 400 ? 2 : 1,
            ],
        ];
        if (! empty($span['parent_span_id'])) {
            $otlpSpan['parentSpanId'] = $span['parent_span_id'];
        }

        $body = [
            'resourceSpans' => [[
                'resource' => [
                    'attributes' => [[
                        'key' => 'service.name',
                        'value' => ['stringValue' => (string) config('openobserve.service_name')],
                    ]],
                ],
                'scopeSpans' => [[
                    'spans' => [$otlpSpan],
                ]],
            ]],
        ];

        $this->postJson($this->otlpUrl('traces'), $body, 'application/json');
    }

    private function shouldSample(): bool
    {
        $rate = (float) config('openobserve.trace_sample_rate', 1.0);
        if ($rate >= 1.0) {
            return true;
        }
        if ($rate <= 0.0) {
            return false;
        }

        return (mt_rand() / mt_getrandmax()) <= $rate;
    }

    private function jsonIngestUrl(string $stream): string
    {
        $org = rawurlencode((string) config('openobserve.org'));
        $stream = rawurlencode($stream);

        return (string) config('openobserve.url') . "/api/{$org}/{$stream}/_json";
    }

    private function otlpUrl(string $signal): string
    {
        $org = rawurlencode((string) config('openobserve.org'));

        return (string) config('openobserve.url') . "/api/{$org}/v1/{$signal}";
    }

    /**
     * @param  array<mixed>|list<array<string, mixed>>  $body
     */
    private function postJson(string $url, array $body, string $contentType = 'application/json'): void
    {
        try {
            $pending = Http::timeout((int) config('openobserve.timeout', 3))
                ->acceptJson()
                ->withHeaders(['Content-Type' => $contentType]);

            $email = (string) config('openobserve.email');
            $password = (string) config('openobserve.password');
            if ($email !== '' && $password !== '') {
                $pending = $pending->withBasicAuth($email, $password);
            }

            $response = $pending->post($url, $body);
            if ($response->failed()) {
                Log::channel('single')->warning('OpenObserve ingest failed.', [
                    'status' => $response->status(),
                    'url' => $this->redactUrl($url),
                ]);
            }
        } catch (Throwable $exception) {
            Log::channel('single')->warning('OpenObserve ingest error.', [
                'error' => $exception->getMessage(),
                'url' => $this->redactUrl($url),
            ]);
        }
    }

    private function redactUrl(string $url): string
    {
        return preg_replace('#://([^@/]+)@#', '://***@', $url) ?? $url;
    }
}
