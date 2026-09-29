<?php

declare(strict_types=1);

namespace App\Support\Code;

/**
 * @deprecated Use SiteCodeCanonicalizer::resolve() instead.
 */
final class OriginDateIdQualifier
{
    /**
     * @return non-empty-string|null
     */
    public static function qualify(?string $raw, string $slug): ?string
    {
        return SiteCodeCanonicalizer::resolve($slug, [$raw]);
    }
}
