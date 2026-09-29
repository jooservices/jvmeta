<?php

declare(strict_types=1);

namespace App\Services\Crawler;

use JOOservices\CrawlerX\Dto\CrawlListResultDto;
use JOOservices\CrawlerX\Dto\Entity\GalleryDto;
use JOOservices\CrawlerX\Dto\Entity\MovieDto;
use JOOservices\CrawlerX\Dto\Entity\PerformerDto;

final readonly class CrawlerxFetchResult
{
    public function __construct(
        public bool $ok,
        public ?CrawlListResultDto $list = null,
        public ?MovieDto $movie = null,
        public ?PerformerDto $performer = null,
        public ?GalleryDto $gallery = null,
        public ?string $errorCode = null,
        public ?string $errorMessage = null,
    ) {}

    public static function success(CrawlListResultDto $list): self
    {
        return new self(ok: true, list: $list);
    }

    public static function movie(MovieDto $movie): self
    {
        return new self(ok: true, movie: $movie);
    }

    public static function performer(PerformerDto $performer): self
    {
        return new self(ok: true, performer: $performer);
    }

    public static function gallery(GalleryDto $gallery): self
    {
        return new self(ok: true, gallery: $gallery);
    }

    public static function failure(string $errorCode, string $errorMessage): self
    {
        return new self(ok: false, errorCode: $errorCode, errorMessage: $errorMessage);
    }
}
