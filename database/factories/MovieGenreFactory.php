<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Genre;
use App\Models\Movie;
use App\Models\MovieGenre;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MovieGenre> */
class MovieGenreFactory extends Factory
{
    protected $model = MovieGenre::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['movie_id' => Movie::factory(), 'genre_id' => Genre::factory(), 'source_slug' => fake()->slug(2), 'crawled_at' => now()];
    }
}
