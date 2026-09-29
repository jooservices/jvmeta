<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Performer;
use App\Models\Movie;
use App\Models\MoviePerformer;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MoviePerformer> */
class MoviePerformerFactory extends Factory
{
    protected $model = MoviePerformer::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['movie_id' => Movie::factory(), 'performer_id' => Performer::factory(), 'source_slug' => fake()->slug(2), 'crawled_at' => now()];
    }
}
