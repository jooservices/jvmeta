<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Services\Crawler\CrawlerxClient;
use App\Services\Crawler\CrawlerxFetchResult;

final class FakeCrawlerxClient extends CrawlerxClient
{
    public function __construct(
        private readonly CrawlerxFetchResult $listing,
        private readonly CrawlerxFetchResult $detail,
        private readonly ?CrawlerxFetchResult $performerListing = null,
        private readonly ?CrawlerxFetchResult $performerDetail = null,
        private readonly ?CrawlerxFetchResult $gallery = null,
    ) {}

    public function fetchListing(string $sourceSlug, string $url): CrawlerxFetchResult
    {
        return $this->listing;
    }

    public function fetchDetail(string $sourceSlug, string $url): CrawlerxFetchResult
    {
        return $this->detail;
    }

    public function fetchPerformerListing(string $sourceSlug, string $url): CrawlerxFetchResult
    {
        return $this->performerListing ?? $this->listing;
    }

    public function fetchPerformerDetail(string $sourceSlug, string $url): CrawlerxFetchResult
    {
        return $this->performerDetail ?? $this->detail;
    }

    public function fetchGallery(string $sourceSlug, string $url): CrawlerxFetchResult
    {
        return $this->gallery ?? CrawlerxFetchResult::failure(self::ERROR_PARSE_FAILED, 'No gallery fixture.');
    }
}
