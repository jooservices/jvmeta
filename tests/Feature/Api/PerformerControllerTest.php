<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Performer;
use App\Models\PerformerAlias;
use App\Models\Movie;
use App\Models\MoviePerformer;
use App\Services\Auth\ApiKeyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PerformerControllerTest extends TestCase
{
    use RefreshDatabase;

    private string $apiKey;

    protected function setUp(): void
    {
        parent::setUp();

        $this->apiKey = app(ApiKeyService::class)->create(fake()->words(2, true))->plaintext;
    }

    public function test_performers_require_api_key(): void
    {
        $this->getJson('/api/v1/performers')
            ->assertUnauthorized()
            ->assertJsonPath('type', 'unauthorized')
            ->assertJsonMissingPath('data');
    }

    public function test_show_requires_api_key(): void
    {
        $performer = Performer::factory()->create();

        $this->getJson('/api/v1/performers/' . $performer->id)
            ->assertUnauthorized()
            ->assertJsonPath('type', 'unauthorized')
            ->assertJsonMissingPath('data');
    }

    public function test_search_matches_name_romaji(): void
    {
        $name = fake()->name();
        $performer = Performer::factory()->create(['name_romaji' => $name]);

        $response = $this->getJson('/api/v1/performers?q=' . urlencode($name), [
            'X-API-Key' => $this->apiKey,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.0.id', $performer->id);
    }

    public function test_search_matches_name_kanji(): void
    {
        $name = fake()->name();
        $performer = Performer::factory()->create(['name_kanji' => $name]);

        $response = $this->getJson('/api/v1/performers?q=' . urlencode($name), [
            'X-API-Key' => $this->apiKey,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.0.id', $performer->id);
    }

    public function test_search_matches_name_kana(): void
    {
        $name = fake()->name();
        $performer = Performer::factory()->create(['name_kana' => $name]);

        $response = $this->getJson('/api/v1/performers?q=' . urlencode($name), [
            'X-API-Key' => $this->apiKey,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.0.id', $performer->id);
    }

    public function test_search_matches_alias_ac53(): void
    {
        $performer = Performer::factory()->create();
        $alias = fake()->unique()->words(2, true);
        PerformerAlias::create([
            'performer_id' => $performer->id,
            'alias' => $alias,
            'kind' => 'stage',
        ]);

        $response = $this->getJson('/api/v1/performers?q=' . urlencode($alias), [
            'X-API-Key' => $this->apiKey,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.0.id', $performer->id)
            ->assertJsonPath('data.0.aliases.0', $alias);
    }

    public function test_same_name_different_sources_remain_separate_ac52(): void
    {
        $name = fake()->name();
        $first = Performer::factory()->create(['name_romaji' => $name]);
        $second = Performer::factory()->create(['name_romaji' => $name]);

        $this->assertNotSame(
            [$first->source_slug, $first->external_id],
            [$second->source_slug, $second->external_id],
        );

        $response = $this->getJson('/api/v1/performers?q=' . urlencode($name), [
            'X-API-Key' => $this->apiKey,
        ]);

        $response->assertOk()
            ->assertJsonCount(2, 'data');

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($first->id, $ids);
        $this->assertContains($second->id, $ids);
        $this->assertNotSame($first->id, $second->id);
    }

    public function test_single_title_performer_is_returned_ac54(): void
    {
        $performer = Performer::factory()->create();
        $movie = Movie::factory()->create();
        MoviePerformer::factory()->create([
            'movie_id' => $movie->id,
            'performer_id' => $performer->id,
            'source_slug' => $performer->source_slug,
        ]);

        $response = $this->getJson('/api/v1/performers/' . $performer->id, [
            'X-API-Key' => $this->apiKey,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.id', $performer->id)
            ->assertJsonPath('data.linked_title_count', 1);
    }

    public function test_linked_title_count_counts_all_movie_performers_ac51(): void
    {
        $performer = Performer::factory()->create();
        $movies = Movie::factory()->count(3)->create();
        foreach ($movies as $movie) {
            MoviePerformer::factory()->create([
                'movie_id' => $movie->id,
                'performer_id' => $performer->id,
                'source_slug' => $performer->source_slug,
            ]);
        }

        $response = $this->getJson('/api/v1/performers/' . $performer->id, [
            'X-API-Key' => $this->apiKey,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.linked_title_count', 3);
    }

    public function test_multi_performer_movie_links_back_to_all_ac55(): void
    {
        $first = Performer::factory()->create();
        $second = Performer::factory()->create();
        $movie = Movie::factory()->create();
        MoviePerformer::factory()->create([
            'movie_id' => $movie->id,
            'performer_id' => $first->id,
            'source_slug' => $first->source_slug,
        ]);
        MoviePerformer::factory()->create([
            'movie_id' => $movie->id,
            'performer_id' => $second->id,
            'source_slug' => $second->source_slug,
        ]);

        $firstResponse = $this->getJson('/api/v1/performers/' . $first->id, [
            'X-API-Key' => $this->apiKey,
        ]);
        $secondResponse = $this->getJson('/api/v1/performers/' . $second->id, [
            'X-API-Key' => $this->apiKey,
        ]);

        $firstResponse->assertOk()->assertJsonPath('data.linked_title_count', 1);
        $secondResponse->assertOk()->assertJsonPath('data.linked_title_count', 1);
    }

    public function test_show_returns_adr_shape(): void
    {
        $performer = Performer::factory()->create(['profile_url' => fake()->url(), 'image_url' => fake()->imageUrl()]);

        $response = $this->getJson('/api/v1/performers/' . $performer->id, [
            'X-API-Key' => $this->apiKey,
        ]);

        $response->assertOk()
            ->assertJsonStructure(['data' => [
                'uuid',
                'id',
                'name_romaji',
                'name_kanji',
                'name_kana',
                'aliases',
                'birth_date',
                'height_cm',
                'bust',
                'waist',
                'hip',
                'cup',
                'blood_type',
                'bio_text',
                'debut_date',
                'location',
                'attrs',
                'linked_title_count',
                'profile_url',
                'image_url',
            ]]);
    }

    public function test_index_returns_summary_shape(): void
    {
        Performer::factory()->create([
            'bust' => 90,
            'bio_text' => fake()->sentence(),
        ]);

        $response = $this->getJson('/api/v1/performers', [
            'X-API-Key' => $this->apiKey,
        ]);

        $response->assertOk()
            ->assertJsonStructure(['data' => [[
                'uuid',
                'id',
                'name_romaji',
                'name_kanji',
                'name_kana',
                'aliases',
                'image_url',
                'profile_url',
            ]]]);

        $row = $response->json('data.0');
        $this->assertIsArray($row);
        $this->assertArrayNotHasKey('bust', $row);
        $this->assertArrayNotHasKey('bio_text', $row);
        $this->assertArrayNotHasKey('linked_title_count', $row);
    }

    public function test_show_unknown_id_returns_performer_not_found(): void
    {
        $response = $this->getJson('/api/v1/performers/999999', [
            'X-API-Key' => $this->apiKey,
        ]);

        $response->assertNotFound()
            ->assertJsonPath('type', 'performer_not_found');
    }

    public function test_search_paginates_with_per_page(): void
    {
        Performer::factory()->count(5)->create();

        $response = $this->getJson('/api/v1/performers?per_page=2', [
            'X-API-Key' => $this->apiKey,
        ]);

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.pagination.total', 5)
            ->assertJsonPath('meta.pagination.per_page', 2)
            ->assertJsonPath('meta.pagination.last_page', 3);
    }

    public function test_search_without_q_returns_all_performers(): void
    {
        $performers = Performer::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/performers', [
            'X-API-Key' => $this->apiKey,
        ]);

        $response->assertOk()
            ->assertJsonPath('meta.pagination.total', 3);

        $ids = collect($response->json('data'))->pluck('id')->all();
        foreach ($performers as $performer) {
            $this->assertContains($performer->id, $ids);
        }
    }

    public function test_q_shorter_than_two_chars_returns_invalid_filter(): void
    {
        $response = $this->getJson('/api/v1/performers?q=a', [
            'X-API-Key' => $this->apiKey,
        ]);

        $response->assertBadRequest()
            ->assertJsonPath('type', 'invalid_filter');
    }

    public function test_per_page_above_100_returns_invalid_filter(): void
    {
        $response = $this->getJson('/api/v1/performers?per_page=101', [
            'X-API-Key' => $this->apiKey,
        ]);

        $response->assertBadRequest()
            ->assertJsonPath('type', 'invalid_filter');
    }
}
