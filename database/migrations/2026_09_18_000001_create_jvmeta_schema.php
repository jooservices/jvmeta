<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $isPostgres = DB::getDriverName() === 'pgsql';

        if ($isPostgres) {
            DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
        }

        Schema::create('movies', function (Blueprint $table) use ($isPostgres): void {
            $table->id();
            $table->text('display_code');
            $table->text('code_normalized')->unique();
            $table->text('title_jp')->nullable();
            $table->text('title_en')->nullable();
            $table->date('release_date')->nullable();
            $table->integer('runtime_minutes')->nullable();
            $table->smallInteger('censored')->nullable();
            $table->text('maker')->nullable();
            $table->text('label')->nullable();
            $table->text('series')->nullable();
            $table->decimal('community_score', 4, 2)->nullable();
            $table->smallInteger('completeness_tier');
            if (! $isPostgres) {
                $table->text('search_vector')->nullable();
            }
            $table->timestampTz('delisted_at')->nullable();
            $table->boolean('needs_review')->default(false);
            $table->timestampTz('first_seen_at');
            $table->timestampTz('updated_at');
            $table->timestampTz('crawled_at');

            $table->index(['release_date', 'id'], 'movies_release_date_id_idx');
            $table->index(['updated_at', 'id'], 'movies_updated_at_id_idx');
        });

        if ($isPostgres) {
            DB::statement("ALTER TABLE movies ADD COLUMN search_vector tsvector GENERATED ALWAYS AS (to_tsvector('simple', coalesce(title_jp, '') || ' ' || coalesce(title_en, '') || ' ' || coalesce(display_code, '') || ' ' || coalesce(code_normalized, ''))) STORED");
            DB::statement('CREATE INDEX movies_release_date_id_desc_idx ON movies (release_date DESC, id DESC)');
            DB::statement('CREATE INDEX movies_updated_at_id_desc_idx ON movies (updated_at DESC, id DESC)');
            DB::statement('CREATE INDEX movies_community_score_id_desc_idx ON movies (community_score DESC NULLS LAST, id DESC)');
            DB::statement('CREATE INDEX movies_search_vector_gin_idx ON movies USING gin (search_vector)');
            DB::statement('CREATE INDEX movies_title_jp_trgm_gin_idx ON movies USING gin (title_jp gin_trgm_ops)');
            DB::statement('CREATE INDEX movies_title_en_trgm_gin_idx ON movies USING gin (title_en gin_trgm_ops)');
            Schema::table('movies', function (Blueprint $table): void {
                $table->dropIndex('movies_release_date_id_idx');
                $table->dropIndex('movies_updated_at_id_idx');
            });
        } else {
            Schema::table('movies', function (Blueprint $table): void {
                $table->index(['community_score', 'id'], 'movies_community_score_id_idx');
                $table->index('search_vector', 'movies_search_vector_idx');
                $table->index('title_jp', 'movies_title_jp_idx');
                $table->index('title_en', 'movies_title_en_idx');
            });
        }

        Schema::create('movie_codes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('movie_id')->constrained('movies');
            $table->text('code');
            $table->text('code_normalized');
            $table->text('kind');
            $table->text('source_slug');
            $table->text('source_url')->nullable();
            $table->timestampTz('crawled_at');

            $table->unique(['code_normalized', 'source_slug']);
            $table->index('code_normalized');
        });

        Schema::create('movie_observations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('movie_id')->constrained('movies');
            $table->text('field');
            $table->text('value')->nullable();
            $table->text('value_hash');
            $table->text('source_slug');
            $table->text('source_url')->nullable();
            $table->timestampTz('crawled_at');
            $table->boolean('is_primary')->default(false);

            $table->index(['movie_id', 'field']);
        });

        Schema::create('genres', function (Blueprint $table): void {
            $table->increments('id');
            $table->text('label_normalized')->unique();
            $table->text('label_raw')->nullable();
        });

        Schema::create('movie_genres', function (Blueprint $table): void {
            $table->foreignId('movie_id')->constrained('movies');
            $table->unsignedInteger('genre_id');
            $table->text('source_slug');
            $table->timestampTz('crawled_at');

            $table->primary(['movie_id', 'genre_id', 'source_slug']);
            $table->foreign('genre_id')->references('id')->on('genres');
            $table->index('genre_id');
        });

        Schema::create('performers', function (Blueprint $table): void {
            $table->id();
            $table->text('source_slug');
            $table->text('external_id');
            $table->text('name_romaji')->nullable();
            $table->text('name_kanji')->nullable();
            $table->text('name_kana')->nullable();
            $table->text('profile_url')->nullable();
            $table->text('image_url')->nullable();
            $table->timestampTz('crawled_at');

            $table->unique(['source_slug', 'external_id']);
        });

        Schema::create('performer_aliases', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('performer_id')->constrained('performers');
            $table->text('alias');
            $table->text('kind');

            $table->unique(['performer_id', 'alias']);
        });

        Schema::create('movie_performers', function (Blueprint $table): void {
            $table->foreignId('movie_id')->constrained('movies');
            $table->foreignId('performer_id')->constrained('performers');
            $table->text('source_slug');
            $table->timestampTz('crawled_at');

            $table->primary(['movie_id', 'performer_id']);
            $table->index('performer_id');
        });

        Schema::create('movie_media', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('movie_id')->constrained('movies');
            $table->text('kind');
            $table->text('url');
            $table->jsonb('meta')->nullable();
            $table->text('source_slug');
            $table->timestampTz('crawled_at');

            $table->unique(['movie_id', 'kind', 'url']);
        });

        Schema::create('sources', function (Blueprint $table): void {
            $table->text('slug')->primary();
            $table->text('name');
            $table->text('base_url');
            $table->boolean('enabled');
            $table->smallInteger('priority');
            $table->boolean('needs_proxy')->default(false);
            $table->decimal('gap_seconds_default');
            $table->decimal('gap_seconds_min')->nullable();
            $table->decimal('gap_seconds_max')->nullable();
            $table->decimal('gap_seconds_current');
            $table->integer('consecutive_failures')->default(0);
            $table->text('circuit_state')->default('closed');
            $table->timestampTz('circuit_opened_at')->nullable();
            $table->timestampTz('last_success_at')->nullable();
            $table->timestampTz('last_error_at')->nullable();
            $table->text('last_error')->nullable();
            $table->jsonb('soft404_markers')->nullable();
        });

        Schema::create('crawl_queue', function (Blueprint $table): void {
            $table->id();
            $table->text('source_slug');
            $table->text('url');
            $table->text('kind');
            $table->text('status')->default('pending');
            $table->integer('attempts')->default(0);
            $table->integer('max_attempts')->default(3);
            $table->timestampTz('next_attempt_at')->nullable();
            $table->timestampTz('claimed_at')->nullable();
            $table->text('locked_by')->nullable();
            $table->text('last_error')->nullable();

            $table->unique(['source_slug', 'url']);
        });

        Schema::create('crawl_runs', function (Blueprint $table): void {
            $table->id();
            $table->text('source_slug');
            $table->timestampTz('started_at');
            $table->timestampTz('finished_at')->nullable();
            $table->text('status');
            $table->integer('pages_fetched')->default(0);
            $table->integer('movies_new')->default(0);
            $table->integer('movies_updated')->default(0);
            $table->integer('failures')->default(0);
            $table->integer('proxy_requests')->default(0);
            $table->bigInteger('proxy_bytes')->default(0);
            $table->integer('browser_fetches')->default(0);
        });

        Schema::create('crawl_events', function (Blueprint $table): void {
            $table->id();
            $table->text('source_slug');
            $table->text('kind');
            $table->text('url')->nullable();
            $table->jsonb('detail')->nullable();
            $table->timestampTz('created_at');
        });

        Schema::create('review_flags', function (Blueprint $table): void {
            $table->id();
            $table->bigInteger('movie_id')->nullable();
            $table->text('code_normalized')->nullable();
            $table->text('source_slug');
            $table->integer('consecutive_failures');
            $table->text('reason');
            $table->timestampTz('flagged_at');
            $table->timestampTz('resolved_at')->nullable();
        });

        Schema::create('api_keys', function (Blueprint $table): void {
            $table->id();
            $table->text('prefix');
            $table->text('key_hash')->unique();
            $table->text('label');
            $table->text('status')->default('active');
            $table->timestampTz('revoked_at')->nullable();
            $table->integer('abuse_rpm')->default(60);
            $table->timestampTz('created_at');
        });

        Schema::create('api_usage_log', function (Blueprint $table): void {
            $table->id();
            $table->bigInteger('api_key_id')->nullable();
            $table->text('endpoint');
            $table->text('method');
            $table->smallInteger('status_code');
            $table->integer('response_ms')->nullable();
            $table->text('ip_hash')->nullable();
            $table->timestampTz('created_at');

            $table->index(['api_key_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_usage_log');
        Schema::dropIfExists('api_keys');
        Schema::dropIfExists('review_flags');
        Schema::dropIfExists('crawl_events');
        Schema::dropIfExists('crawl_runs');
        Schema::dropIfExists('crawl_queue');
        Schema::dropIfExists('sources');
        Schema::dropIfExists('movie_media');
        Schema::dropIfExists('movie_performers');
        Schema::dropIfExists('performer_aliases');
        Schema::dropIfExists('performers');
        Schema::dropIfExists('movie_genres');
        Schema::dropIfExists('genres');
        Schema::dropIfExists('movie_observations');
        Schema::dropIfExists('movie_codes');
        Schema::dropIfExists('movies');
    }
};
