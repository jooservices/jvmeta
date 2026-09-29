<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Performer;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Performer> */
class PerformerFactory extends Factory
{
    protected $model = Performer::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'uuid' => fake()->uuid(),
            'source_slug' => fake()->slug(2),
            'external_id' => fake()->unique()->uuid(),
            'name_romaji' => fake()->optional()->name(),
            'name_kanji' => null,
            'name_kana' => null,
            'attrs' => [],
            'needs_review' => false,
            'profile_url' => fake()->optional()->url(),
            'image_url' => fake()->optional()->imageUrl(),
            'first_seen_at' => now(),
            'updated_at' => now(),
            'crawled_at' => now(),
        ];
    }
}
