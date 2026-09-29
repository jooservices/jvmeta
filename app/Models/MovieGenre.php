<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\MovieGenreFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MovieGenre extends Model
{
    /** @use HasFactory<MovieGenreFactory> */
    use HasFactory;

    protected $table = 'movie_genres';
    public $incrementing = false;
    public $timestamps = false;
    /** @var list<string> */
    protected $fillable = ['movie_id', 'genre_id', 'source_slug', 'crawled_at'];

    /** @return BelongsTo<Movie, $this> */
    public function movie(): BelongsTo
    {
        return $this->belongsTo(Movie::class);
    }

    /** @return BelongsTo<Genre, $this> */
    public function genre(): BelongsTo
    {
        return $this->belongsTo(Genre::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['movie_id' => 'integer', 'genre_id' => 'integer', 'crawled_at' => 'datetime'];
    }
}
