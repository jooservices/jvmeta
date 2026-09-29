<?php

declare(strict_types=1);

namespace App\Data\Crawl;

use App\Support\Code\NormalizedCode;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

final readonly class MovieDraft
{
    /**
     * @param  list<string>  $genres
     * @param  list<PerformerDraft>  $performers
     * @param  list<string>  $directors
     * @param  array{magnets: list<array<string, mixed>>, hls: list<array<string, mixed>>, gallery: list<array<string, mixed>>, score: float|null}  $extras
     */
    public function __construct(
        public string $sourceSlug,
        public string $sourceUrl,
        public string $code,
        public ?string $titleJp = null,
        public ?string $titleEn = null,
        public ?string $description = null,
        public ?CarbonImmutable $releaseDate = null,
        public ?int $runtimeMinutes = null,
        public ?bool $censored = null,
        public ?string $maker = null,
        public ?string $label = null,
        public ?string $series = null,
        public array $genres = [],
        public array $performers = [],
        public array $directors = [],
        public ?string $coverUrl = null,
        public array $extras = ['magnets' => [], 'hls' => [], 'gallery' => [], 'score' => null],
        public ?CarbonImmutable $crawledAt = null,
    ) {
        if (trim($this->sourceSlug) === '') {
            throw new InvalidArgumentException('Movie source slug cannot be empty.');
        }

        if (trim($this->sourceUrl) === '') {
            throw new InvalidArgumentException('Movie source URL cannot be empty.');
        }

        NormalizedCode::from($this->code);

        if ($this->runtimeMinutes !== null && $this->runtimeMinutes < 1) {
            throw new InvalidArgumentException('Runtime minutes must be positive.');
        }

        foreach ($this->genres as $genre) {
            if (trim($genre) === '') {
                throw new InvalidArgumentException('Genres must be non-empty strings.');
            }
        }

        foreach ($this->directors as $director) {
            if (trim($director) === '') {
                throw new InvalidArgumentException('Directors must be non-empty strings.');
            }
        }

        $this->assertExtras($this->extras);
    }

    /**
     * @param  list<string>  $genres
     * @param  list<PerformerDraft>  $performers
     * @param  list<string>  $directors
     * @param  array{magnets: list<array<string, mixed>>, hls: list<array<string, mixed>>, gallery: list<array<string, mixed>>, score: float|null}  $extras
     */
    public static function from(
        string $sourceSlug,
        string $sourceUrl,
        string $code,
        ?string $titleJp = null,
        ?string $titleEn = null,
        ?string $description = null,
        ?CarbonImmutable $releaseDate = null,
        ?int $runtimeMinutes = null,
        ?bool $censored = null,
        ?string $maker = null,
        ?string $label = null,
        ?string $series = null,
        array $genres = [],
        array $performers = [],
        array $directors = [],
        ?string $coverUrl = null,
        array $extras = ['magnets' => [], 'hls' => [], 'gallery' => [], 'score' => null],
        ?CarbonImmutable $crawledAt = null,
    ): self {
        return new self(
            $sourceSlug,
            $sourceUrl,
            $code,
            $titleJp,
            $titleEn,
            $description,
            $releaseDate,
            $runtimeMinutes,
            $censored,
            $maker,
            $label,
            $series,
            $genres,
            $performers,
            $directors,
            $coverUrl,
            $extras,
            $crawledAt,
        );
    }

    /** @param array<string, mixed> $extras */
    private function assertExtras(array $extras): void
    {
        foreach (['magnets', 'hls', 'gallery'] as $key) {
            if (! array_key_exists($key, $extras) || ! is_array($extras[$key])) {
                throw new InvalidArgumentException("Movie extras {$key} must be a list.");
            }
        }

        if (! array_key_exists('score', $extras) || (! is_float($extras['score']) && $extras['score'] !== null)) {
            throw new InvalidArgumentException('Movie extras score must be a float or null.');
        }
    }
}
