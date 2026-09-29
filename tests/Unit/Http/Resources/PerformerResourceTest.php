<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Resources;

use App\Http\Resources\PerformerResource;
use App\Http\Resources\PerformerSummaryResource;
use App\Models\Performer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

final class PerformerResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_summary_exposes_slim_keys_only(): void
    {
        $performer = Performer::factory()->create([
            'bust' => 86,
            'bio_text' => fake()->paragraph(),
        ]);

        $payload = (new PerformerSummaryResource($performer))->toArray(Request::create('/api/v1/performers'));

        self::assertSame([
            'uuid',
            'id',
            'name_romaji',
            'name_kanji',
            'name_kana',
            'aliases',
            'image_url',
            'profile_url',
        ], array_keys($payload));
        self::assertArrayNotHasKey('bust', $payload);
        self::assertArrayNotHasKey('bio_text', $payload);
        self::assertArrayNotHasKey('linked_title_count', $payload);
    }

    public function test_detail_exposes_full_profile_keys(): void
    {
        $performer = Performer::factory()->create();
        $performer->loadCount('movies');

        $payload = (new PerformerResource($performer))->toArray(Request::create('/api/v1/performers/1'));

        self::assertSame($performer->uuid, $payload['uuid']);
        self::assertArrayHasKey('bust', $payload);
        self::assertArrayHasKey('bio_text', $payload);
        self::assertArrayHasKey('attrs', $payload);
        self::assertArrayHasKey('linked_title_count', $payload);
    }
}
