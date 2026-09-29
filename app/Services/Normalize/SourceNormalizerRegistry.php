<?php

declare(strict_types=1);

namespace App\Services\Normalize;

final class SourceNormalizerRegistry
{
    /**
     * @param  list<SourceNormalizer>  $normalizers
     */
    public function __construct(private readonly array $normalizers) {}

    public function forSlug(string $slug): ?SourceNormalizer
    {
        foreach ($this->normalizers as $normalizer) {
            if ($normalizer->supports($slug)) {
                return $normalizer;
            }
        }

        return null;
    }
}
