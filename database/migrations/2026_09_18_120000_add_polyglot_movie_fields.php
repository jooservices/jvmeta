<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Additive schema for model C: uuid public ids, rich fields, Mongo link pivots.
 * Table `movies` remains Postgres SoR for movies (API exposes /movies).
 */
return new class extends Migration {
    public function up(): void
    {
        $isPostgres = DB::getDriverName() === 'pgsql';

        Schema::table('movies', function (Blueprint $table) use ($isPostgres): void {
            $table->uuid('uuid')->nullable()->after('id');
            $table->text('description')->nullable()->after('title_en');
            $table->text('cover_url')->nullable()->after('community_score');
            $table->text('cover_thumb_url')->nullable()->after('cover_url');
            if ($isPostgres) {
                $table->jsonb('attrs')->nullable()->after('cover_thumb_url');
            } else {
                $table->json('attrs')->nullable()->after('cover_thumb_url');
            }
        });

        foreach (DB::table('movies')->whereNull('uuid')->orderBy('id')->cursor() as $row) {
            DB::table('movies')->where('id', $row->id)->update(['uuid' => (string) Str::uuid()]);
        }

        if ($isPostgres) {
            DB::statement("UPDATE movies SET attrs = '{}'::jsonb WHERE attrs IS NULL");
            DB::statement('ALTER TABLE movies ALTER COLUMN uuid SET NOT NULL');
            DB::statement('ALTER TABLE movies ALTER COLUMN attrs SET DEFAULT \'{}\'::jsonb');
            DB::statement('ALTER TABLE movies ALTER COLUMN attrs SET NOT NULL');
        } else {
            DB::table('movies')->whereNull('attrs')->update(['attrs' => '{}']);
        }

        Schema::table('movies', function (Blueprint $table): void {
            $table->unique('uuid');
        });

        Schema::table('performers', function (Blueprint $table) use ($isPostgres): void {
            $table->uuid('uuid')->nullable()->after('id');
            $table->date('birth_date')->nullable()->after('name_kana');
            $table->integer('height_cm')->nullable()->after('birth_date');
            $table->integer('bust')->nullable()->after('height_cm');
            $table->integer('waist')->nullable()->after('bust');
            $table->integer('hip')->nullable()->after('waist');
            $table->text('cup')->nullable()->after('hip');
            $table->text('blood_type')->nullable()->after('cup');
            $table->text('bio_text')->nullable()->after('blood_type');
            $table->date('debut_date')->nullable()->after('bio_text');
            $table->text('location')->nullable()->after('debut_date');
            if ($isPostgres) {
                $table->jsonb('attrs')->nullable()->after('image_url');
            } else {
                $table->json('attrs')->nullable()->after('image_url');
            }
            $table->boolean('needs_review')->default(false)->after('attrs');
            $table->timestampTz('first_seen_at')->nullable()->after('needs_review');
            $table->timestampTz('updated_at')->nullable()->after('first_seen_at');
        });

        foreach (DB::table('performers')->whereNull('uuid')->orderBy('id')->cursor() as $row) {
            DB::table('performers')->where('id', $row->id)->update([
                'uuid' => (string) Str::uuid(),
                'first_seen_at' => $row->crawled_at,
                'updated_at' => $row->crawled_at,
            ]);
        }

        if ($isPostgres) {
            DB::statement("UPDATE performers SET attrs = '{}'::jsonb WHERE attrs IS NULL");
            DB::statement('ALTER TABLE performers ALTER COLUMN uuid SET NOT NULL');
            DB::statement('ALTER TABLE performers ALTER COLUMN attrs SET DEFAULT \'{}\'::jsonb');
            DB::statement('ALTER TABLE performers ALTER COLUMN attrs SET NOT NULL');
        } else {
            DB::table('performers')->whereNull('attrs')->update(['attrs' => '{}']);
        }

        Schema::table('performers', function (Blueprint $table): void {
            $table->unique('uuid');
        });

        Schema::create('movie_sources', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('movie_id')->constrained('movies');
            $table->text('site_slug');
            $table->text('mongo_id');
            $table->text('source_url')->nullable();
            $table->text('source_code')->nullable();
            $table->timestampTz('crawled_at');

            $table->unique(['site_slug', 'mongo_id']);
            $table->unique(['movie_id', 'site_slug']);
        });

        Schema::create('performer_sources', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('performer_id')->constrained('performers');
            $table->text('site_slug');
            $table->text('mongo_id');
            $table->text('external_id')->nullable();
            $table->text('source_url')->nullable();
            $table->timestampTz('crawled_at');

            $table->unique(['site_slug', 'mongo_id']);
        });

        Schema::create('movie_credits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('movie_id')->constrained('movies');
            $table->text('role');
            $table->text('name');
            $table->text('site_slug');
            $table->timestampTz('crawled_at');

            $table->unique(['movie_id', 'role', 'name', 'site_slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movie_credits');
        Schema::dropIfExists('performer_sources');
        Schema::dropIfExists('movie_sources');

        Schema::table('performers', function (Blueprint $table): void {
            $table->dropUnique(['uuid']);
            $table->dropColumn([
                'uuid', 'birth_date', 'height_cm', 'bust', 'waist', 'hip', 'cup',
                'blood_type', 'bio_text', 'debut_date', 'location', 'attrs',
                'needs_review', 'first_seen_at', 'updated_at',
            ]);
        });

        Schema::table('movies', function (Blueprint $table): void {
            $table->dropUnique(['uuid']);
            $table->dropColumn(['uuid', 'description', 'cover_url', 'cover_thumb_url', 'attrs']);
        });
    }
};
