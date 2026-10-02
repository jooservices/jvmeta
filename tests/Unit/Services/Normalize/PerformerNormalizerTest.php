<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Normalize;

use App\Services\Normalize\PerformerNormalizer;
use Faker\Factory;
use Faker\Generator;
use JOOservices\CrawlerX\Dto\Entity\PerformerDto;
use PHPUnit\Framework\TestCase;

final class PerformerNormalizerTest extends TestCase
{
    private Generator $faker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->faker = Factory::create();
    }

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

    public function test_maps_aisex_separate_measurements_when_size_raw_is_missing(): void
    {
        $dto = PerformerDto::fromParsed(
            $this->faker->uuid(),
            $this->faker->name(),
            [
                'bust_raw' => '88cm',
                'cup_size' => 'e',
                'waist_raw' => '58 cm',
                'hip_raw' => '86',
            ],
            $this->faker->url(),
        );

        $draft = (new PerformerNormalizer())->normalize($dto, 'aisex', $this->faker->url());

        self::assertNotNull($draft);
        self::assertSame(88, $draft->bust);
        self::assertSame(58, $draft->waist);
        self::assertSame(86, $draft->hip);
        self::assertSame('E', $draft->cup);
    }

    public function test_uses_cup_size_fallback_when_size_raw_has_measurements_without_cup(): void
    {
        $dto = PerformerDto::fromParsed(
            $this->faker->uuid(),
            $this->faker->name(),
            [
                'size_raw' => 'B90 W60 H89',
                'cup_size' => 'F',
            ],
            $this->faker->url(),
        );

        $draft = (new PerformerNormalizer())->normalize($dto, 'avfan_profiles', $this->faker->url());

        self::assertNotNull($draft);
        self::assertSame(90, $draft->bust);
        self::assertSame(60, $draft->waist);
        self::assertSame(89, $draft->hip);
        self::assertSame('F', $draft->cup);
    }

    public function test_size_raw_values_take_precedence_over_separate_measurements(): void
    {
        $dto = PerformerDto::fromParsed(
            $this->faker->uuid(),
            $this->faker->name(),
            [
                'size_raw' => 'B88(E) W58 H86',
                'bust_raw' => '91',
                'cup_size' => 'G',
                'waist_raw' => '62',
                'hip_raw' => '93',
            ],
            $this->faker->url(),
        );

        $draft = (new PerformerNormalizer())->normalize($dto, 'avjoho', $this->faker->url());

        self::assertNotNull($draft);
        self::assertSame(88, $draft->bust);
        self::assertSame(58, $draft->waist);
        self::assertSame(86, $draft->hip);
        self::assertSame('E', $draft->cup);
    }

    public function test_invalid_separate_measurements_remain_null(): void
    {
        $dto = PerformerDto::fromParsed(
            $this->faker->uuid(),
            $this->faker->name(),
            [
                'bust_raw' => 'unknown',
                'cup_size' => 'E cup',
                'waist_raw' => '29cm',
                'hip_raw' => '301cm',
            ],
            $this->faker->url(),
        );

        $draft = (new PerformerNormalizer())->normalize($dto, 'aisex', $this->faker->url());

        self::assertNotNull($draft);
        self::assertNull($draft->bust);
        self::assertNull($draft->waist);
        self::assertNull($draft->hip);
        self::assertNull($draft->cup);
    }
}
