<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Movie;
use App\Models\Performer;
use App\Services\Search\ElasticsearchIndexer;
use Illuminate\Console\Command;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Create/update the Elasticsearch index mappings (dense_vector embedding fields)
 * and optionally re-embed + re-index all existing movies and performers.
 */
final class EsSetupCommand extends Command
{
    protected $signature = 'es:setup {--reindex : Re-embed and re-index all movies and performers}';

    protected $description = 'Create/update ES mappings for dense_vector embeddings and (optionally) re-index all data.';

    public function handle(): int
    {
        $dim = (int) config('elasticsearch.embed_dim', 384);
        $vectorProp = ['type' => 'dense_vector', 'dims' => $dim, 'index' => true, 'similarity' => 'cosine'];

        $this->ensureIndex((string) config('elasticsearch.movies_index'), 'embedding_movie', $vectorProp);
        $this->ensureIndex((string) config('elasticsearch.performers_index'), 'embedding_performer', $vectorProp);

        if ($this->option('reindex')) {
            $this->reindex();
        }

        $this->info('ES setup done.');

        return self::SUCCESS;
    }

    /** @param array<string, mixed> $vectorProp */
    private function ensureIndex(string $index, string $embeddingField, array $vectorProp): void
    {
        $host = rtrim((string) config('elasticsearch.host'), '/');

        try {
            if ($this->http()->head("{$host}/{$index}")->ok()) {
                $this->http()->put("{$host}/{$index}/_mapping", [
                    'properties' => [$embeddingField => $vectorProp],
                ]);
                $this->info("Updated mapping: {$index}");
            } else {
                $this->http()->put("{$host}/{$index}", [
                    'settings' => ['number_of_shards' => 1, 'number_of_replicas' => 0],
                    'mappings' => [
                        'properties' => [
                            'uuid' => ['type' => 'keyword'],
                            $embeddingField => $vectorProp,
                        ],
                    ],
                ]);
                $this->info("Created index: {$index}");
            }
        } catch (\Throwable $e) {
            $this->warn("ES mapping failed for {$index}: {$e->getMessage()}");
        }
    }

    private function reindex(): void
    {
        $indexer = app(ElasticsearchIndexer::class);

        foreach (Movie::cursor() as $movie) {
            $indexer->indexMovie($movie);
            $this->line("  movie #{$movie->id} indexed");
        }

        foreach (Performer::cursor() as $performer) {
            $indexer->indexPerformer($performer);
            $this->line("  performer #{$performer->id} indexed");
        }
    }

    private function http(): PendingRequest
    {
        $request = Http::timeout(10);

        if (! (bool) config('elasticsearch.verify_ssl', false)) {
            $request = $request->withoutVerifying();
        }

        return $request;
    }
}
