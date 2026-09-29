<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Code;

use App\Support\Code\NormalizedCode;
use App\Support\Code\OriginDateIdQualifier;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** @deprecated Thin wrapper around SiteCodeCanonicalizer — keep until callers migrate. */
final class OriginDateIdQualifierTest extends TestCase
{
    #[DataProvider('onepondoProvider')]
    public function test_qualifies_onepondo_date_ids(string $raw, string $expected): void
    {
        self::assertSame($expected, OriginDateIdQualifier::qualify($raw, 'onepondo'));
        self::assertSame(
            str_replace(['-', '_'], '', $expected),
            NormalizedCode::from($expected)->value(),
        );
    }

    /**
     * @return list<array{string, string}>
     */
    public static function onepondoProvider(): array
    {
        return [
            ['060426_001', '1PONDO-060426_001'],
            ['060426-001', '1PONDO-060426_001'],
            ['1pondo-060426_001', '1PONDO-060426_001'],
            ['1PON-060426_001', '1PONDO-060426_001'],
            ['1PONDO-060426_001', '1PONDO-060426_001'],
        ];
    }

    #[DataProvider('caribbeancomProvider')]
    public function test_qualifies_caribbeancom_date_ids(string $raw, string $expected): void
    {
        self::assertSame($expected, OriginDateIdQualifier::qualify($raw, 'caribbeancom'));
        self::assertSame(
            str_replace(['-', '_'], '', $expected),
            NormalizedCode::from($expected)->value(),
        );
    }

    /**
     * @return list<array{string, string}>
     */
    public static function caribbeancomProvider(): array
    {
        return [
            ['080826-001', 'CARIBBEANCOM-080826-001'],
            ['080826_001', 'CARIBBEANCOM-080826-001'],
            ['carib-080826-001', 'CARIBBEANCOM-080826-001'],
            ['CARIBBEANCOM-080826-001', 'CARIBBEANCOM-080826-001'],
        ];
    }

    public function test_returns_null_for_blank_or_invalid(): void
    {
        self::assertNull(OriginDateIdQualifier::qualify(null, 'onepondo'));
        self::assertNull(OriginDateIdQualifier::qualify('   ', 'onepondo'));
        self::assertNull(OriginDateIdQualifier::qualify('NOT-A-DATE', 'onepondo'));
    }
}
