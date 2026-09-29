<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Code;

use App\Support\Code\NormalizedCode;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class NormalizedCodeTest extends TestCase
{
    public function test_normalizes_valid_dvd_code(): void
    {
        $code = NormalizedCode::from('SSIS-001');

        self::assertSame('SSIS001', $code->value());
        self::assertSame('SSIS-001', $code->display());
    }

    public function test_rejects_malformed_code(): void
    {
        $this->expectException(InvalidArgumentException::class);

        NormalizedCode::from('not-a-code');
    }

    public function test_preserves_distinct_prefix_identity(): void
    {
        $fc2Ppv = NormalizedCode::from('FC2-PPV-1234567');
        $fc2 = NormalizedCode::from('FC2-1234567');

        self::assertSame('FC2PPV1234567', $fc2Ppv->value());
        self::assertSame('FC21234567', $fc2->value());
        self::assertNotSame($fc2Ppv->value(), $fc2->value());
    }

    public function test_trims_whitespace_and_uppercases_prefix(): void
    {
        self::assertSame('SSIS001', NormalizedCode::from(" \tssis-001\n")->value());
    }

    public function test_strips_leading_zeros_from_numeric_suffix(): void
    {
        self::assertSame('SSIS001', NormalizedCode::from('SSIS-0001')->value());
        self::assertSame('SSIS001', NormalizedCode::from('SSIS-001')->value());
        self::assertSame('SSIS001', NormalizedCode::from('SSIS-1')->value());
        self::assertSame('SSIS001', NormalizedCode::from('SSIS001')->value());
        self::assertSame('SSIS001', NormalizedCode::from('ssis-001')->value());
    }

    public function test_accepts_studio_qualified_date_ids(): void
    {
        self::assertSame('1PONDO060426001', NormalizedCode::from('1PONDO-060426_001')->value());
        self::assertSame('1PONDO060426-001', NormalizedCode::from('1PONDO-060426_001')->display());
        self::assertSame('CARIBBEANCOM080826001', NormalizedCode::from('CARIBBEANCOM-080826-001')->value());
        self::assertSame('CARIBBEANCOM080826-001', NormalizedCode::from('CARIBBEANCOM-080826-001')->display());
    }

    public function test_normalizes_all_zero_suffix(): void
    {
        self::assertSame('SSIS000', NormalizedCode::from('SSIS-000')->value());
        self::assertSame('SSIS000', NormalizedCode::from('SSIS-0')->value());
    }

    public function test_rejects_non_numeric_suffix(): void
    {
        $this->expectException(InvalidArgumentException::class);

        NormalizedCode::from('SSIS-ABC');
    }
}
