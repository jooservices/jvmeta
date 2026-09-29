<?php

declare(strict_types=1);

namespace App\Services\Persist;

use App\Models\Performer;
use App\Models\Movie;
use App\Models\MovieCode;
use App\Models\MovieMedia;
use App\Support\Code\NormalizedCode;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use InvalidArgumentException;
use JOOservices\CrawlerX\Dto\Entity\GalleryDto;
use JOOservices\CrawlerX\Dto\Entity\PhotoDto;

/**
 * Attaches eporner (and similar) gallery photo URLs to a matching movie,
 * and upserts thin performer rows from gallery performer name labels.
 */
final class GalleryPersister
{
    public function persist(string $sourceSlug, string $url, GalleryDto $gallery): void
    {
        $crawledAt = CarbonImmutable::now();
        $movie = $this->resolveMovie($gallery->title);

        if ($movie instanceof Movie) {
            foreach ($gallery->photos as $photo) {
                $imageUrl = $this->photoUrl($photo);
                if ($imageUrl === null) {
                    continue;
                }

                MovieMedia::query()->updateOrCreate(
                    [
                        'movie_id' => (int) $movie->id,
                        'kind' => MovieMedia::KIND_GALLERY,
                        'url' => $imageUrl,
                    ],
                    [
                        'source_slug' => $sourceSlug,
                        'meta' => [
                            'gallery_url' => $url,
                            'thumbnail_url' => $photo->thumbnailUrl,
                            'position' => $photo->position,
                        ],
                        'crawled_at' => $crawledAt,
                    ],
                );
            }
        }

        foreach ($gallery->performers as $name) {
            $trimmed = trim($name);
            if ($trimmed === '') {
                continue;
            }

            $externalId = Str::slug($trimmed);
            if ($externalId === '') {
                $externalId = 'name:' . mb_strtolower($trimmed);
            }

            $performer = Performer::query()->firstOrCreate(
                ['source_slug' => $sourceSlug, 'external_id' => $externalId],
                [
                    'name_romaji' => $trimmed,
                    'attrs' => [],
                    'needs_review' => false,
                    'first_seen_at' => $crawledAt,
                    'updated_at' => $crawledAt,
                    'crawled_at' => $crawledAt,
                ],
            );

            if ($movie instanceof Movie) {
                $movie->performers()->syncWithoutDetaching([
                    (int) $performer->id => [
                        'source_slug' => $sourceSlug,
                        'crawled_at' => $crawledAt,
                    ],
                ]);
            }
        }
    }

    private function resolveMovie(?string $title): ?Movie
    {
        if ($title === null || trim($title) === '') {
            return null;
        }

        if (preg_match('/\b([A-Z]{2,10})-?(\d{2,6})\b/i', $title, $m) !== 1) {
            return null;
        }

        try {
            $normalized = NormalizedCode::from($m[1] . '-' . $m[2]);
        } catch (InvalidArgumentException) {
            return null;
        }

        $movieId = MovieCode::query()
            ->where('code_normalized', $normalized->value())
            ->value('movie_id');

        if (! is_numeric($movieId)) {
            $movieId = Movie::query()->where('code_normalized', $normalized->value())->value('id');
        }

        if (! is_numeric($movieId)) {
            return null;
        }

        $movie = Movie::query()->find((int) $movieId);

        return $movie instanceof Movie ? $movie : null;
    }

    private function photoUrl(PhotoDto $photo): ?string
    {
        foreach ([$photo->imageUrl, $photo->url, $photo->thumbnailUrl] as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return trim($candidate);
            }
        }

        return null;
    }
}
