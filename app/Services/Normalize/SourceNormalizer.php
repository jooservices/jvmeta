<?php

declare(strict_types=1);

namespace App\Services\Normalize;

use App\Data\Crawl\MovieDraft;
use Carbon\CarbonImmutable;
use JOOservices\CrawlerX\Dto\Entity\MovieDto;

interface SourceNormalizer
{
    public function supports(string $slug): bool;

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function normalizeMovie(MovieDto $movie, array $metadata, string $slug, string $sourceUrl, CarbonImmutable $crawledAt): MovieDraft;
}
