<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Genre;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Genre> */
class GenreFactory extends Factory
{
    protected $model = Genre::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $label = fake()->unique()->words(2, true);

        return ['label_normalized' => Str::slug($label), 'label_raw' => fake()->optional()->words(2, true)];
    }
}
