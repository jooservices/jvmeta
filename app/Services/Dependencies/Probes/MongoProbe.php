<?php

declare(strict_types=1);

namespace App\Services\Dependencies\Probes;

use App\Services\Dependencies\DependencyProbe;
use Closure;
use RuntimeException;
use MongoDB\Driver\Command;
use MongoDB\Driver\Manager;
use Throwable;

final class MongoProbe implements DependencyProbe
{
    /** @var Closure(): void */
    private readonly Closure $ping;

    /**
     * @param  Closure(): void|null  $ping  Optional probe seam for tests.
     */
    public function __construct(?Closure $ping = null)
    {
        $this->ping = $ping ?? static function (): void {
            $dsn = (string) config('database.connections.mongodb.dsn', '');
            if ($dsn === '') {
                throw new RuntimeException('MongoDB DSN is not configured.');
            }

            $manager = new Manager($dsn, ['serverSelectionTimeoutMS' => 1500]);
            $manager->executeCommand('admin', new Command(['ping' => 1]));
        };
    }

    public function probe(): bool
    {
        try {
            ($this->ping)();

            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
