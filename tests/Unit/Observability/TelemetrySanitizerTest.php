<?php

declare(strict_types=1);

namespace Tests\Unit\Observability;

use App\Observability\TelemetrySanitizer;
use Tests\TestCase;

final class TelemetrySanitizerTest extends TestCase
{
    public function test_strips_denied_keys_and_nested_domain_fields(): void
    {
        $title = fake()->sentence(3);
        $code = 'STARS-' . fake()->numberBetween(100, 999);

        $clean = (new TelemetrySanitizer())->sanitizeContext([
            'source_slug' => 'javdb',
            'title' => $title,
            'code' => $code,
            'url' => 'https://example.test/' . $code,
            'nested' => [
                'error_code' => 'blocked',
                'value' => $title,
            ],
            'count' => 3,
        ]);

        $this->assertSame('javdb', $clean['source_slug']);
        $this->assertSame(3, $clean['count']);
        $this->assertArrayNotHasKey('title', $clean);
        $this->assertArrayNotHasKey('code', $clean);
        $this->assertArrayNotHasKey('url', $clean);
        $this->assertSame(['error_code' => 'blocked'], $clean['nested']);
        $this->assertStringNotContainsString($title, json_encode($clean, JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString($code, json_encode($clean, JSON_THROW_ON_ERROR));
    }

    public function test_crawl_detail_allow_list_drops_error_message_with_code(): void
    {
        $code = 'ABC-' . fake()->numberBetween(1000, 9999);
        $clean = (new TelemetrySanitizer())->sanitizeCrawlDetail([
            'error_code' => 'parse_failed',
            'reason' => 'unknown_source',
            'error' => 'Failed for ' . $code,
            'queued' => 2,
            'items' => 5,
            'entity_type' => 'movie',
            'url' => 'https://example.test',
        ]);

        $this->assertSame([
            'error_code' => 'parse_failed',
            'reason' => 'unknown_source',
            'queued' => 2,
            'items' => 5,
            'entity_type' => 'movie',
        ], $clean);
    }
}
