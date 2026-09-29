<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Movie;
use App\Models\MovieMedia;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MovieMedia> */
class MovieMediaFactory extends Factory
{
    protected $model = MovieMedia::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['movie_id' => Movie::factory(), 'kind' => fake()->randomElement([MovieMedia::KIND_MAGNET, MovieMedia::KIND_PIKPAK, MovieMedia::KIND_HLS, MovieMedia::KIND_GALLERY, MovieMedia::KIND_SAMPLE]), 'url' => fake()->unique()->url(), 'meta' => null, 'source_slug' => fake()->slug(2), 'crawled_at' => now()];
    }
}
