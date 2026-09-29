<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CrawlQueue;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CrawlQueue> */
class CrawlQueueFactory extends Factory
{
    protected $model = CrawlQueue::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['source_slug' => fake()->slug(2), 'url' => fake()->unique()->url(), 'kind' => fake()->randomElement([CrawlQueue::KIND_LISTING, CrawlQueue::KIND_DETAIL, CrawlQueue::KIND_PERFORMER_LISTING, CrawlQueue::KIND_PERFORMER_DETAIL]), 'status' => CrawlQueue::STATUS_PENDING, 'attempts' => 0, 'max_attempts' => 3, 'next_attempt_at' => null, 'claimed_at' => null, 'locked_by' => null, 'last_error' => null];
    }
}
