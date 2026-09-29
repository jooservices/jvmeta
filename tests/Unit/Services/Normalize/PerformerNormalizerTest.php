<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Normalize;

use App\Services\Normalize\PerformerNormalizer;
use JOOservices\CrawlerX\Dto\Entity\PerformerDto;
use PHPUnit\Framework\TestCase;

final class PerformerNormalizerTest extends TestCase
{
    public function test_maps_warashi_shaped_performer_dto(): void
    {
        $dto = PerformerDto::fromParsed(
            '12345',
            'Airi Suzumura',
            [
                'name_japanese' => '涼村あいり',
                'name_romaji' => 'Airi Suzumura',
                'profile_image_url' => 'https://cdn.test/a.jpg',
                'birth_date_raw' => '1992-09-04',
                'height_raw' => '158cm',
                'size_raw' => 'B88(E) W58 H86',
                'blood_type' => 'A',
                'birthplace' => 'Tokyo',
                'aliases' => ['AiriS'],
                'tags' => ['slender'],
                'raw_profile' => 'Long bio',
            ],
            'https://warashi.test/p/12345',
        );

        $draft = (new PerformerNormalizer())->normalize($dto, 'warashi', 'https://warashi.test/p/12345');

        self::assertNotNull($draft);
        self::assertSame('warashi', $draft->sourceSlug);
        self::assertSame('12345', $draft->externalId);
        self::assertSame('Airi Suzumura', $draft->nameRomaji);
        self::assertSame('涼村あいり', $draft->nameKanji);
        self::assertSame(158, $draft->heightCm);
        self::assertSame(88, $draft->bust);
        self::assertSame('E', $draft->cup);
        self::assertSame('1992-09-04', $draft->birthDate);
        self::assertSame(['AiriS'], $draft->aliases);
        self::assertSame('Long bio', $draft->bioText);
    }
}
