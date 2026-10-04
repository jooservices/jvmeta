<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Dependencies;

use App\Services\Dependencies\DependencyProbe;
use App\Services\Dependencies\Probes\PostgresProbe;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

final class PostgresProbeTest extends TestCase
{
    public function test_it_runs_a_select_one_query_and_reports_a_healthy_connection(): void
    {
        $connection = Mockery::mock(Connection::class);
        $connection->shouldReceive('select')
            ->once()
            ->with('select 1')
            ->andReturn([['?column?' => 1]]);

        DB::shouldReceive('connection')->once()->andReturn($connection);

        $probe = new PostgresProbe();

        self::assertInstanceOf(DependencyProbe::class, $probe);
        self::assertTrue($probe->probe());
    }

    public function test_it_reports_an_unhealthy_connection_when_the_query_fails(): void
    {
        $connection = Mockery::mock(Connection::class);
        $connection->shouldReceive('select')
            ->once()
            ->with('select 1')
            ->andThrow(new \RuntimeException('database unavailable'));

        DB::shouldReceive('connection')->once()->andReturn($connection);

        self::assertFalse((new PostgresProbe())->probe());
    }
}
