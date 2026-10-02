<?php

declare(strict_types=1);

namespace App\Services\Normalize;

use App\Data\Crawl\PerformerDraft;
use JOOservices\CrawlerX\Dto\Entity\PerformerDto;
use Throwable;

/**
 * Maps crawlerx PerformerDto (+ raw metadata) onto PerformerDraft for profile crawl.
 */
final class PerformerNormalizer
{
    public function normalize(PerformerDto $performer, string $sourceSlug, string $url): ?PerformerDraft
    {
        $externalId = $performer->externalId ?? $performer->name;
        if ($externalId === null || trim($externalId) === '') {
            return null;
        }

        $meta = $performer->metadata;
        $size = $this->parseSize($performer->sizeRaw ?? (is_string($meta['size_raw'] ?? null) ? $meta['size_raw'] : null));
        $measurements = $this->measurements($size, $meta);
        $bio = $this->bioText($performer, $meta);
        $attrs = array_filter([
            'tags' => $performer->tags !== [] ? $performer->tags : null,
            'zodiac_sign' => is_string($meta['zodiac_sign'] ?? null) ? $meta['zodiac_sign'] : null,
            'age' => is_string($meta['age'] ?? null) ? $meta['age'] : null,
            'shoe_size_raw' => is_string($meta['shoe_size_raw'] ?? null) ? $meta['shoe_size_raw'] : null,
            'hair_length' => is_string($meta['hair_length'] ?? null) ? $meta['hair_length'] : null,
            'hair_color' => is_string($meta['hair_color'] ?? null) ? $meta['hair_color'] : null,
            'favorite_count_raw' => is_string($meta['favorite_count_raw'] ?? null) ? $meta['favorite_count_raw'] : null,
        ], static fn(mixed $v): bool => $v !== null);

        $aliases = array_values(array_unique(array_filter(
            $performer->aliases,
            static fn(string $alias): bool => trim($alias) !== '',
        )));

        $nameKana = is_string($meta['name_kana'] ?? null) ? trim($meta['name_kana']) : null;

        return new PerformerDraft(
            sourceSlug: $sourceSlug,
            externalId: trim($externalId),
            nameRomaji: $performer->name ?? (is_string($meta['name_romaji'] ?? null) ? $meta['name_romaji'] : null),
            nameKanji: $performer->nameJapanese,
            nameKana: $nameKana !== '' ? $nameKana : null,
            aliases: $aliases,
            profileUrl: $performer->url ?? $url,
            imageUrl: $performer->profileImageUrl,
            birthDate: $this->dateYmd($performer->birthDateRaw ?? (is_string($meta['birth_date_raw'] ?? null) ? $meta['birth_date_raw'] : null)),
            heightCm: $this->heightCm($performer->heightRaw ?? (is_string($meta['height_raw'] ?? null) ? $meta['height_raw'] : null)),
            bust: $measurements['bust'],
            waist: $measurements['waist'],
            hip: $measurements['hip'],
            cup: $measurements['cup'],
            bloodType: is_string($meta['blood_type'] ?? null) ? $meta['blood_type'] : null,
            bioText: $bio,
            debutDate: $this->dateYmd(is_string($meta['debut_date_raw'] ?? null) ? $meta['debut_date_raw'] : null),
            location: is_string($meta['birthplace'] ?? null) ? $meta['birthplace'] : null,
            attrs: $attrs,
        );
    }

    /**
     * @param array{bust: ?int, waist: ?int, hip: ?int, cup: ?string} $size
     * @param array<string, mixed> $meta
     *
     * @return array{bust: ?int, waist: ?int, hip: ?int, cup: ?string}
     */
    private function measurements(array $size, array $meta): array
    {
        return [
            'bust' => $size['bust'] ?? $this->measurementRaw($meta['bust_raw'] ?? null),
            'waist' => $size['waist'] ?? $this->measurementRaw($meta['waist_raw'] ?? null),
            'hip' => $size['hip'] ?? $this->measurementRaw($meta['hip_raw'] ?? null),
            'cup' => $size['cup'] ?? $this->cupRaw($meta['cup_size'] ?? null) ?? $this->cupRaw($meta['cup'] ?? null),
        ];
    }

    private function measurementRaw(mixed $raw): ?int
    {
        if (! is_string($raw) || trim($raw) === '') {
            return null;
        }

        if (preg_match('/(\d{1,3})/', $raw, $m) !== 1) {
            return null;
        }

        $value = (int) $m[1];

        return $value >= 30 && $value <= 300 ? $value : null;
    }

    private function cupRaw(mixed $raw): ?string
    {
        if (! is_string($raw) || trim($raw) === '') {
            return null;
        }

        $cup = strtoupper(trim($raw));

        return preg_match('/^[A-Z0-9]{1,20}$/', $cup) === 1 ? $cup : null;
    }

    /** @param array<string, mixed> $meta */
    private function bioText(PerformerDto $performer, array $meta): ?string
    {
        if ($performer->rawProfile !== []) {
            $text = $performer->rawProfile['text'] ?? null;
            if (is_string($text) && trim($text) !== '') {
                return trim($text);
            }
        }

        $raw = $meta['raw_profile'] ?? null;
        if (is_string($raw) && trim($raw) !== '') {
            return trim($raw);
        }

        return null;
    }

    private function heightCm(?string $raw): ?int
    {
        if ($raw === null || trim($raw) === '') {
            return null;
        }

        if (preg_match('/(\d{2,3})/', $raw, $m) === 1) {
            $cm = (int) $m[1];

            return $cm >= 100 && $cm <= 250 ? $cm : null;
        }

        return null;
    }

    /**
     * @return array{bust: ?int, waist: ?int, hip: ?int, cup: ?string}
     */
    private function parseSize(?string $raw): array
    {
        $empty = ['bust' => null, 'waist' => null, 'hip' => null, 'cup' => null];
        if ($raw === null || trim($raw) === '') {
            return $empty;
        }

        $cup = null;
        if (preg_match('/\(([A-Za-z0-9]+)\)/', $raw, $cupMatch) === 1) {
            $cup = strtoupper($cupMatch[1]);
        }

        if (preg_match('/(\d{2,3}).*?(\d{2,3}).*?(\d{2,3})/', $raw, $m) === 1) {
            return [
                'bust' => (int) $m[1],
                'waist' => (int) $m[2],
                'hip' => (int) $m[3],
                'cup' => $cup,
            ];
        }

        return ['bust' => null, 'waist' => null, 'hip' => null, 'cup' => $cup];
    }

    private function dateYmd(?string $raw): ?string
    {
        if ($raw === null || trim($raw) === '') {
            return null;
        }

        try {
            return \Carbon\CarbonImmutable::parse(trim($raw))->format('Y-m-d');
        } catch (Throwable) {
            if (preg_match('/(\d{4})[-\/.](\d{1,2})[-\/.](\d{1,2})/', $raw, $m) === 1) {
                return sprintf('%04d-%02d-%02d', (int) $m[1], (int) $m[2], (int) $m[3]);
            }

            return null;
        }
    }
}
