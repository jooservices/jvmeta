<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Crawl;

use App\Models\Source;
use App\Services\Crawl\SourceThrottle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SourceThrottleTest extends TestCase
{
    use RefreshDatabase;

    public function test_failure_increases_gap_and_clamps_to_maximum(): void
    {
        $source = Source::factory()->create([
            'gap_seconds_current' => 20,
            'gap_seconds_min' => 10,
            'gap_seconds_max' => 30,
        ]);

        $throttle = new SourceThrottle();

        $afterFirstFailure = $throttle->onFailure($source);
        $afterSecondFailure = $throttle->onFailure($afterFirstFailure);

        $this->assertSame(30.0, (float) $afterFirstFailure->gap_seconds_current);
        $this->assertSame(30.0, (float) $afterSecondFailure->gap_seconds_current);
    }

    public function test_success_decreases_gap_and_clamps_to_minimum(): void
    {
        $source = Source::factory()->create([
            'gap_seconds_current' => 11,
            'gap_seconds_min' => 10,
            'gap_seconds_max' => 30,
        ]);

        $updated = (new SourceThrottle())->onSuccess($source);

        $this->assertSame(10.0, (float) $updated->gap_seconds_current);
    }

    public function test_current_gap_uses_current_source_value(): void
    {
        $source = Source::factory()->create(['gap_seconds_current' => 12.5]);

        $this->assertSame(12.5, (new SourceThrottle())->currentGap($source->slug));
    }
}
