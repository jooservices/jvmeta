<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Search;

use App\Models\Performer;
use App\Services\Search\ElasticsearchIndexer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class ElasticsearchIndexerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_performer_embeds_rich_profile_and_uses_performer_field(): void
    {
        $bio = fake()->paragraph();
        $location = fake()->city();
        $performer = Performer::factory()->create([
            'bio_text' => $bio,
            'location' => $location,
        ]);
        $alias = fake()->unique()->name();
        $performer->aliases()->create(['alias' => $alias, 'kind' => 'stage']);

        Http::fake(static fn(ClientRequest $request) => str_ends_with($request->url(), '/v1/embeddings')
            ? Http::response(['data' => [['embedding' => array_fill(0, 384, 0.1)]], 'model' => 'jvmeta-passage'])
            : Http::response(['result' => 'created']));

        app(ElasticsearchIndexer::class)->indexPerformer($performer);

        Http::assertSent(static function (ClientRequest $request) use ($bio, $location, $alias): bool {
            if (! str_ends_with($request->url(), '/v1/embeddings')) {
                return false;
            }

            $input = $request->data()['input'][0] ?? '';

            return is_string($input)
                && str_contains($input, $bio)
                && str_contains($input, $location)
                && str_contains($input, $alias);
        });

        Http::assertSent(static function (ClientRequest $request) use ($performer): bool {
            if (! str_ends_with($request->url(), '/jvmeta_performers/_doc/' . $performer->uuid)) {
                return false;
            }

            return array_key_exists('embedding_performer', $request->data());
        });
    }

    public function test_semantic_performer_search_queries_the_performer_vector_field(): void
    {
        $uuid = fake()->uuid();

        Http::fake(static function (ClientRequest $request) use ($uuid) {
            if (str_ends_with($request->url(), '/v1/embeddings')) {
                return Http::response(['data' => [['embedding' => array_fill(0, 384, 0.2)]], 'model' => 'jvmeta-query']);
            }

            return Http::response(['hits' => ['hits' => [['_source' => ['uuid' => $uuid]]]]]);
        });

        $uuids = app(ElasticsearchIndexer::class)->semanticPerformerUuids(fake()->sentence(), 5);

        $this->assertSame([$uuid], $uuids);
        Http::assertSent(static function (ClientRequest $request): bool {
            if (! str_ends_with($request->url(), '/jvmeta_performers/_search')) {
                return false;
            }

            return ($request->data()['knn']['field'] ?? null) === 'embedding_performer';
        });
    }

    public function test_semantic_movie_search_queries_the_movie_vector_field(): void
    {
        $uuid = fake()->uuid();

        Http::fake(static function (ClientRequest $request) use ($uuid) {
            if (str_ends_with($request->url(), '/v1/embeddings')) {
                return Http::response(['data' => [['embedding' => array_fill(0, 384, 0.3)]], 'model' => 'jvmeta-query']);
            }

            return Http::response(['hits' => ['hits' => [['_source' => ['uuid' => $uuid]]]]]);
        });

        $uuids = app(ElasticsearchIndexer::class)->semanticMovieUuids(fake()->sentence(), 5);

        $this->assertSame([$uuid], $uuids);
        Http::assertSent(static function (ClientRequest $request): bool {
            if (! str_ends_with($request->url(), '/jvmeta_movies/_search')) {
                return false;
            }

            return ($request->data()['knn']['field'] ?? null) === 'embedding_movie';
        });
    }
}
