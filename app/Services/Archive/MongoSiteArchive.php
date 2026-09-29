<?php

declare(strict_types=1);

namespace App\Services\Archive;

use Carbon\CarbonImmutable;
use MongoDB\Client;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;
use App\Observability\ObservabilityEmitter;
use Throwable;

/**
 * Writes flexible per-site crawl payloads to MongoDB (archive only; not end-user read).
 * Collection name = site slug (onejav, javdb, …).
 */
final class MongoSiteArchive
{
    private ?Client $client = null;

    private bool $available = true;

    public function upsertMovie(string $siteSlug, string $url, array $payload, ?string $code = null): ?string
    {
        return $this->upsert($siteSlug, [
            'entity_type' => 'movie',
            'url' => $url,
            'code' => $code,
            'payload' => $payload,
            'parse_ok' => true,
            'crawled_at' => new UTCDateTime(CarbonImmutable::now()),
        ], ['url' => $url]);
    }

    public function upsertPerformer(string $siteSlug, string $url, array $payload, ?string $externalId = null): ?string
    {
        $filter = $externalId !== null && $externalId !== ''
            ? ['entity_type' => 'performer', 'external_id' => $externalId]
            : ['entity_type' => 'performer', 'url' => $url];

        return $this->upsert($siteSlug, [
            'entity_type' => 'performer',
            'url' => $url,
            'external_id' => $externalId,
            'payload' => $payload,
            'parse_ok' => true,
            'crawled_at' => new UTCDateTime(CarbonImmutable::now()),
        ], $filter);
    }

    /**
     * @param  array<string, mixed>  $document
     * @param  array<string, mixed>  $filter
     */
    private function upsert(string $siteSlug, array $document, array $filter): ?string
    {
        $started = hrtime(true);
        $client = $this->client();
        if ($client === null) {
            app(ObservabilityEmitter::class)->emitDependency('mongo', 'upsert', false, 0);

            return null;
        }

        try {
            $collection = $client->selectDatabase((string) config('mongodb.database'))->selectCollection($siteSlug);
            $result = $collection->updateOne(
                $filter,
                ['$set' => $document],
                ['upsert' => true],
            );

            $id = null;
            if ($result->getUpsertedId() instanceof ObjectId) {
                $id = (string) $result->getUpsertedId();
            } else {
                $existing = $collection->findOne($filter, ['projection' => ['_id' => 1]]);
                if ($existing !== null && isset($existing['_id'])) {
                    $id = (string) $existing['_id'];
                }
            }

            app(ObservabilityEmitter::class)->emitDependency(
                'mongo',
                'upsert',
                $id !== null,
                (int) round((hrtime(true) - $started) / 1_000_000),
            );

            return $id;
        } catch (Throwable) {
            $this->available = false;
            app(ObservabilityEmitter::class)->emitDependency(
                'mongo',
                'upsert',
                false,
                (int) round((hrtime(true) - $started) / 1_000_000),
            );

            return null;
        }
    }

    private function client(): ?Client
    {
        if (! $this->available) {
            return null;
        }

        if ($this->client instanceof Client) {
            return $this->client;
        }

        if (! class_exists(Client::class) || ! extension_loaded('mongodb')) {
            $this->available = false;

            return null;
        }

        try {
            $uri = config('mongodb.uri');
            if (! is_string($uri) || $uri === '') {
                $host = (string) config('mongodb.host', 'mongo');
                $port = (int) config('mongodb.port', 27017);
                $uri = "mongodb://{$host}:{$port}";
            }

            $this->client = new Client($uri);
        } catch (Throwable) {
            $this->available = false;

            return null;
        }

        return $this->client;
    }
}
