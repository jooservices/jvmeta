<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Movie;
use App\Models\MovieObservation;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MovieObservation> */
class MovieObservationFactory extends Factory
{
    protected $model = MovieObservation::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $value = fake()->optional()->sentence(3);

        return ['movie_id' => Movie::factory(), 'field' => fake()->randomElement(['title_jp', 'title_en', 'release_date', 'runtime', 'maker', 'label', 'series', 'cover_url', 'community_score']), 'value' => $value, 'value_hash' => hash('sha256', (string) $value), 'source_slug' => fake()->slug(2), 'source_url' => fake()->optional()->url(), 'crawled_at' => now(), 'is_primary' => fake()->boolean(20)];
    }
}
