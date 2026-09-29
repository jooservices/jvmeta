<?php

declare(strict_types=1);

namespace App\Services\Normalize\Normalizers;

use App\Data\Crawl\MovieDraft;
use App\Services\Normalize\NormalizationFailedException;
use Carbon\CarbonImmutable;
use JOOservices\CrawlerX\Dto\Entity\MovieDto;

/**
 * Normalizer for Jable. The adapter reports the HLS manifest as a single
 * `stream` descriptor (manifest_url, video_id, expires_at, poster_url) and
 * the site serves English titles, so the title lands in title_en and the HLS
 * entry keeps the descriptor reference metadata.
 */
final class JableSourceNormalizer extends DefaultSourceNormalizer
{
    public function supports(string $slug): bool
    {
        return $slug === 'jable';
    }

    protected function titleLocale(string $slug): string
    {
        return 'en';
    }

    public function normalizeMovie(MovieDto $movie, array $metadata, string $slug, string $sourceUrl, CarbonImmutable $crawledAt): MovieDraft
    {
        if (! is_array($metadata['stream'] ?? null)) {
            throw new NormalizationFailedException('Jable detail did not contain an HLS stream descriptor.');
        }

        return parent::normalizeMovie($movie, $metadata, $slug, $sourceUrl, $crawledAt);
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return list<array<string, mixed>>
     */
    protected function hlsFrom(array $metadata, string $slug): array
    {
        $stream = $metadata['stream'] ?? null;
        if (! is_array($stream) || ! is_string($stream['manifest_url'] ?? null) || trim($stream['manifest_url']) === '') {
            return [];
        }

        $entry = ['url' => trim($stream['manifest_url'])];
        foreach (['video_id', 'expires_at', 'poster_url'] as $key) {
            if (is_string($stream[$key] ?? null) && $stream[$key] !== '') {
                $entry[$key] = $stream[$key];
            }
        }

        return [$entry];
    }
}
