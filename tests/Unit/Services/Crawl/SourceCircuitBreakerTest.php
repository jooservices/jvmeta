<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Crawl;

use App\Models\Source;
use App\Services\Crawl\SourceCircuitBreaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SourceCircuitBreakerTest extends TestCase
{
    use RefreshDatabase;

    public function test_closed_circuit_opens_when_consecutive_failures_reach_threshold(): void
    {
        $source = Source::factory()->create(['consecutive_failures' => 2]);

        $updated = (new SourceCircuitBreaker(failureThreshold: 3))->recordFailure($source, fake()->sentence());

        $this->assertSame(Source::CIRCUIT_OPEN, $updated->circuit_state);
        $this->assertSame(3, $updated->consecutive_failures);
        $this->assertNotNull($updated->circuit_opened_at);
        $this->assertNotNull($updated->last_error_at);
    }

    public function test_open_circuit_moves_to_half_open_after_cooldown(): void
    {
        $source = Source::factory()->create([
            'circuit_state' => Source::CIRCUIT_OPEN,
            'circuit_opened_at' => now()->subMinutes(10),
        ]);

        $breaker = new SourceCircuitBreaker(cooldownSeconds: 300);

        $this->assertTrue($breaker->isAvailable($source));
        $this->assertSame(Source::CIRCUIT_HALF_OPEN, $source->refresh()->circuit_state);
    }

    public function test_open_circuit_remains_unavailable_before_cooldown(): void
    {
        $source = Source::factory()->create([
            'circuit_state' => Source::CIRCUIT_OPEN,
            'circuit_opened_at' => now()->subMinute(),
        ]);

        $this->assertFalse((new SourceCircuitBreaker(cooldownSeconds: 300))->isAvailable($source));
        $this->assertSame(Source::CIRCUIT_OPEN, $source->refresh()->circuit_state);
    }

    public function test_half_open_circuit_closes_on_success(): void
    {
        $source = Source::factory()->create([
            'circuit_state' => Source::CIRCUIT_HALF_OPEN,
            'consecutive_failures' => 3,
            'last_error' => fake()->sentence(),
        ]);

        $updated = (new SourceCircuitBreaker())->recordSuccess($source);

        $this->assertSame(Source::CIRCUIT_CLOSED, $updated->circuit_state);
        $this->assertSame(0, $updated->consecutive_failures);
        $this->assertNull($updated->last_error);
        $this->assertNotNull($updated->last_success_at);
    }

    public function test_half_open_circuit_reopens_on_failure(): void
    {
        $source = Source::factory()->create([
            'circuit_state' => Source::CIRCUIT_HALF_OPEN,
            'consecutive_failures' => 3,
        ]);

        $updated = (new SourceCircuitBreaker())->recordFailure($source, fake()->sentence());

        $this->assertSame(Source::CIRCUIT_OPEN, $updated->circuit_state);
        $this->assertSame(4, $updated->consecutive_failures);
    }
}
