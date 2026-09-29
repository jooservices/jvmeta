<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Movie;
use App\Models\MovieCode;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MovieCode> */
class MovieCodeFactory extends Factory
{
    protected $model = MovieCode::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $code = fake()->unique()->bothify('???-####');

        return ['movie_id' => Movie::factory(), 'code' => strtoupper($code), 'code_normalized' => str_replace('-', '', strtoupper($code)), 'kind' => fake()->randomElement([MovieCode::KIND_DVD, MovieCode::KIND_UNCENSORED, MovieCode::KIND_FC2, MovieCode::KIND_RE_RELEASE, MovieCode::KIND_LEAK, MovieCode::KIND_BOX_SET]), 'source_slug' => fake()->slug(2), 'source_url' => fake()->optional()->url(), 'crawled_at' => now()];
    }
}
