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
        public bool $retryable = false,
        public ?int $retryAfterSeconds = null,
        /** @var list<array<string, mixed>> crawlerx fetch attempts (failures only in crawlerx 1.3) */
        public array $attempts = [],
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

    /** @param list<array<string, mixed>> $attempts */
    public static function failure(
        string $errorCode,
        string $errorMessage,
        bool $retryable = false,
        ?int $retryAfterSeconds = null,
        array $attempts = [],
    ): self {
        return new self(
            ok: false,
            errorCode: $errorCode,
            errorMessage: $errorMessage,
            retryable: $retryable,
            retryAfterSeconds: $retryAfterSeconds,
            attempts: $attempts,
        );
    }
}
