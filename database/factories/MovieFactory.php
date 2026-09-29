<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Movie;
use App\Support\Code\NormalizedCode;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Movie> */
class MovieFactory extends Factory
{
    protected $model = Movie::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        // Keep display_code / code_normalized aligned with NormalizedCode so
        // lookup (BR-9 zero-strip + pad) resolves factory rows.
        $normalized = NormalizedCode::from(fake()->unique()->bothify('???-####'));

        return [
            'uuid' => fake()->uuid(),
            'display_code' => $normalized->display(),
            'code_normalized' => $normalized->value(),
            'title_jp' => fake()->optional()->sentence(3),
            'title_en' => fake()->optional()->sentence(3),
            'description' => fake()->optional()->paragraph(),
            'release_date' => fake()->unique()->dateTimeBetween('-10 years', 'now')->format('Y-m-d'),
            'runtime_minutes' => fake()->optional()->numberBetween(30, 240),
            'censored' => fake()->optional()->randomElement([Movie::CENSORED_UNCENSORED, Movie::CENSORED_CENSORED]),
            'maker' => fake()->optional()->company(),
            'label' => fake()->optional()->companySuffix(),
            'series' => fake()->optional()->words(2, true),
            'community_score' => fake()->optional()->randomFloat(2, 1, 9.99),
            'cover_url' => fake()->optional()->imageUrl(),
            'attrs' => [],
            'completeness_tier' => fake()->numberBetween(0, 3),
            'delisted_at' => null,
            'needs_review' => false,
            'first_seen_at' => now(),
            'updated_at' => now(),
            'crawled_at' => now(),
        ];
    }
}
