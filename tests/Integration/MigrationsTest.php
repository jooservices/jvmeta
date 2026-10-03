<?php

declare(strict_types=1);

namespace Tests\Integration;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The unit/feature suites migrate sqlite only. Production runs Postgres, so
 * every migration must apply, roll back and re-apply there.
 */
final class MigrationsTest extends BaseTestCase
{
    public function test_migrations_apply_roll_back_and_reapply_on_postgres(): void
    {
        self::assertSame('pgsql', DB::connection()->getDriverName());

        self::assertSame(0, Artisan::call('migrate:fresh', ['--force' => true]), Artisan::output());
        foreach (['movies', 'movie_sources', 'movie_observations', 'performers', 'performer_sources', 'crawl_queue', 'sources'] as $table) {
            self::assertTrue(Schema::hasTable($table), "missing table {$table}");
        }

        self::assertSame(0, Artisan::call('migrate:reset', ['--force' => true]), Artisan::output());
        self::assertFalse(Schema::hasTable('movies'));

        self::assertSame(0, Artisan::call('migrate', ['--force' => true]), Artisan::output());
        self::assertTrue(Schema::hasTable('movies'));
    }
}
