<?php

declare(strict_types=1);

namespace App\Services\Persist;

/**
 * Pure completeness tier calculator (AC-1.5).
 *
 * The tier derives from the number of present core fields out of the 11
 * AC-1.1 fields: code, title_jp, title_en, actresses, cover_url,
 * release_date, runtime, maker, label, series, genres. Buckets:
 * 0-2 => 0, 3-5 => 1, 6-8 => 2, 9-11 => 3.
 *
 * Source-class factor: records whose observations come exclusively from
 * origin-class sources (FC2 and studio sites) are bumped one tier when
 * otherwise rich (>= 6 fields), because the fields they structurally lack
 * (maker/label/series are aggregator metadata) are not lost data. Capped
 * at tier 3. No DB access: callers pass the presence count and the class
 * decision.
 */
final class CompletenessTierCalculator
{
    public const CORE_FIELD_COUNT = 11;

    public const MAX_TIER = 3;

    /** @var list<string> */
    public const ORIGIN_SOURCE_CLASS = ['fc2', 'onepondo', 'caribbeancom', 'heyzo', 'tokyohot', 'duga', 'xcity'];

    public function tier(int $presentFieldCount, bool $originSourceOnly = false): int
    {
        $tier = match (true) {
            $presentFieldCount >= 9 => 3,
            $presentFieldCount >= 6 => 2,
            $presentFieldCount >= 3 => 1,
            default => 0,
        };

        if ($originSourceOnly && $presentFieldCount >= 6) {
            return min(self::MAX_TIER, $tier + 1);
        }

        return $tier;
    }

    public function isOriginSource(string $sourceSlug): bool
    {
        return in_array($sourceSlug, self::ORIGIN_SOURCE_CLASS, true);
    }
}
