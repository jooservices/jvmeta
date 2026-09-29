<?php

declare(strict_types=1);

namespace App\Logging;

use Illuminate\Support\Collection;
use JOOservices\LaravelLogging\Contracts\LogStoreInterface;
use JOOservices\LaravelLogging\DTO\ActivityLogData;
use JOOservices\LaravelLogging\Models\ActivityLogRecord;

/**
 * In-memory LogStore for PHPUnit (no Mongo required).
 */
final class ArrayLogStore implements LogStoreInterface
{
    /** @var list<ActivityLogData> */
    private array $records = [];

    public function prepare(ActivityLogData $data): ActivityLogData
    {
        return $data;
    }

    public function record(ActivityLogData $data): ActivityLogRecord
    {
        $prepared = $this->prepare($data);
        $this->records[] = $prepared;

        return new ActivityLogRecord($prepared->toArray());
    }

    /**
     * @param  list<ActivityLogData>  $records
     * @return Collection<int, ActivityLogRecord>
     */
    public function recordMany(array $records): Collection
    {
        $saved = [];

        foreach ($records as $data) {
            $saved[] = $this->record($data);
        }

        return new Collection($saved);
    }

    /** @return list<ActivityLogData> */
    public function all(): array
    {
        return $this->records;
    }

    public function flush(): void
    {
        $this->records = [];
    }
}
