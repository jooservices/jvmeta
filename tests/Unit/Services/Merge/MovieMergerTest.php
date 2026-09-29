<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Merge;

use App\Data\Crawl\MovieDraft;
use App\Models\Movie;
use App\Services\Merge\MovieMerger;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class MovieMergerTest extends TestCase
{
    use RefreshDatabase;

    public function test_unknown_code_creates_a_fresh_movie_row(): void
    {
        $movieId = (new MovieMerger())->resolveMovieId($this->draft('SSIS-001', 'javdb'));

        $movie = Movie::query()->findOrFail($movieId);
        $this->assertSame('SSIS-001', $movie->display_code);
        $this->assertSame('SSIS001', $movie->code_normalized);
        $this->assertSame(0, $movie->completeness_tier);
        $this->assertFalse($movie->needs_review);
    }

    public function test_exact_code_normalized_variants_resolve_to_the_same_movie(): void
    {
        $merger = new MovieMerger();

        $first = $merger->resolveMovieId($this->draft('SSIS-001', 'javdb'));
        $second = $merger->resolveMovieId($this->draft('ssis-1', 'onejav'));

        $this->assertSame($first, $second);
        $this->assertSame(1, Movie::query()->count());
    }

    public function test_prefix_collision_never_merges(): void
    {
        $merger = new MovieMerger();

        $fc2Ppv = $merger->resolveMovieId($this->draft('FC2-PPV-1234567', 'fc2'));
        $fc2 = $merger->resolveMovieId($this->draft('FC2-1234567', 'fc2'));

        $this->assertNotSame($fc2Ppv, $fc2);
        $this->assertSame(2, Movie::query()->count());
        $this->assertDatabaseHas('movies', ['id' => $fc2Ppv, 'code_normalized' => 'FC2PPV1234567']);
        $this->assertDatabaseHas('movies', ['id' => $fc2, 'code_normalized' => 'FC21234567']);
    }

    public function test_identical_title_but_different_code_never_merges(): void
    {
        $merger = new MovieMerger();

        $first = $merger->resolveMovieId($this->draft('ABC-001', 'javdb', 'Shared Title'));
        $second = $merger->resolveMovieId($this->draft('ABC-002', 'onejav', 'Shared Title'));

        $this->assertNotSame($first, $second);
        $this->assertSame(2, Movie::query()->count());
    }

    public function test_existing_movie_is_resolved_without_creating_a_new_row(): void
    {
        $merger = new MovieMerger();

        $merger->resolveMovieId($this->draft('SSIS-001', 'javdb'));
        $merger->resolveMovieId($this->draft('SSIS001', 'javdb'));

        $this->assertSame(1, Movie::query()->count());
    }

    private function draft(string $code, string $sourceSlug, ?string $titleJp = null): MovieDraft
    {
        return MovieDraft::from(
            sourceSlug: $sourceSlug,
            sourceUrl: "https://{$sourceSlug}.test/v/1",
            code: $code,
            titleJp: $titleJp,
            crawledAt: CarbonImmutable::now(),
        );
    }
}
