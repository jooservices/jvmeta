<?php

declare(strict_types=1);

namespace Tests\Feature\Mcp;

use App\Models\Genre;
use App\Models\Movie;
use App\Models\MovieCode;
use App\Models\MovieGenre;
use App\Models\MovieMedia;
use App\Models\Performer;
use App\Services\Mcp\McpToolService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

final class McpToolServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_definitions_expose_collection_and_detail_tools(): void
    {
        $names = collect(app(McpToolService::class)->definitions())->pluck('name')->all();

        $this->assertSame([
            'lookup_movies',
            'get_movie',
            'search',
            'lookup_performers',
            'get_performer',
        ], $names);
    }

    public function test_lookup_movies_returns_filter_sort_and_cursor_metadata(): void
    {
        $genre = Genre::factory()->create(['label_normalized' => 'drama', 'label_raw' => 'Drama']);
        $movies = Movie::factory()->count(3)->create(['title_en' => 'Drama collection']);
        foreach ($movies as $movie) {
            MovieGenre::factory()->create([
                'movie_id' => $movie->id,
                'genre_id' => $genre->id,
                'source_slug' => fake()->slug(2),
            ]);
        }

        $service = app(McpToolService::class);
        $pageOne = $service->call('lookup_movies', [
            'q' => 'Drama',
            'genre' => 'drama',
            'sort' => 'release_date',
            'per_page' => 2,
        ]);

        $this->assertCount(2, $pageOne['items']);
        $this->assertTrue($pageOne['pagination']['has_more']);
        $this->assertNotEmpty($pageOne['pagination']['next_cursor']);

        $pageTwo = $service->call('lookup_movies', [
            'q' => 'Drama',
            'genre' => 'drama',
            'sort' => 'release_date',
            'per_page' => 2,
            'cursor' => $pageOne['pagination']['next_cursor'],
        ]);

        $this->assertCount(1, $pageTwo['items']);
        $this->assertSame(3, $pageTwo['pagination']['total']);
    }

    public function test_get_movie_returns_full_media_urls_and_thumbnail(): void
    {
        $movie = Movie::factory()->create(['display_code' => 'SSIS-001', 'code_normalized' => 'SSIS001']);
        MovieCode::factory()->create([
            'movie_id' => $movie->id,
            'code' => 'SSIS-001',
            'code_normalized' => 'SSIS001',
        ]);
        MovieMedia::factory()->create([
            'movie_id' => $movie->id,
            'kind' => MovieMedia::KIND_GALLERY,
            'url' => fake()->imageUrl(),
            'meta' => ['thumbnail_url' => fake()->imageUrl()],
        ]);

        $payload = app(McpToolService::class)->call('get_movie', ['code' => 'SSIS-001']);

        $this->assertSame('SSIS-001', $payload['code']);
        $this->assertArrayHasKey('gallery', $payload);
        $this->assertArrayHasKey('thumbnail_url', $payload['gallery'][0]);
    }

    public function test_lookup_performers_searches_bio_and_returns_cursor_metadata(): void
    {
        $bioTerm = fake()->unique()->words(2, true);
        Performer::factory()->count(3)->create(['bio_text' => "Profile {$bioTerm}"]);

        $payload = app(McpToolService::class)->call('lookup_performers', [
            'q' => $bioTerm,
            'sort' => 'name',
            'per_page' => 2,
        ]);

        $this->assertCount(2, $payload['items']);
        $this->assertTrue($payload['pagination']['has_more']);
        $this->assertNotEmpty($payload['pagination']['next_cursor']);
        $this->assertSame(3, $payload['pagination']['total']);
    }

    public function test_lookup_performers_filters_age_and_body_information(): void
    {
        Carbon::setTestNow('2026-09-30');
        $matching = Performer::factory()->create([
            'birth_date' => '1996-01-01',
            'blood_type' => 'A',
            'hip' => 92,
        ]);
        Performer::factory()->create([
            'birth_date' => '1980-01-01',
            'blood_type' => 'A',
            'hip' => 92,
        ]);

        $payload = app(McpToolService::class)->call('lookup_performers', [
            'age_min' => 25,
            'age_max' => 35,
            'blood_type' => 'A',
            'hip_min' => 90,
            'hip_max' => 95,
        ]);

        Carbon::setTestNow();

        $this->assertSame(1, $payload['pagination']['total']);
        $this->assertSame($matching->id, $payload['items'][0]['id']);
    }

    public function test_get_performer_accepts_uuid_and_returns_full_profile(): void
    {
        $performer = Performer::factory()->create([
            'bio_text' => fake()->paragraph(),
            'blood_type' => 'B',
        ]);

        $payload = app(McpToolService::class)->call('get_performer', ['id' => $performer->uuid]);

        $this->assertSame($performer->uuid, $payload['uuid']);
        $this->assertSame($performer->bio_text, $payload['bio_text']);
        $this->assertSame('B', $payload['blood_type']);
    }

    public function test_search_returns_movies_by_natural_language(): void
    {
        $movie = Movie::factory()->create([
            'title_en' => 'A Cheeky Little Devil Schoolgirl At A Tavern Part-time Job',
        ]);
        Movie::factory()->create(['title_en' => 'Unrelated documentary about fishing']);

        $payload = app(McpToolService::class)->call('search', [
            'q' => 'cheeky',
        ]);

        $this->assertSame('cheeky', $payload['query']);
        $this->assertGreaterThanOrEqual(1, $payload['pagination']['total']);
        $this->assertSame($movie->uuid, $payload['items'][0]['uuid']);
    }
}
