<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\MovieMediaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MovieMedia extends Model
{
    /** @use HasFactory<MovieMediaFactory> */
    use HasFactory;

    public const KIND_MAGNET = 'magnet';
    public const KIND_PIKPAK = 'pikpak';
    public const KIND_HLS = 'hls';
    public const KIND_GALLERY = 'gallery';
    public const KIND_SAMPLE = 'sample';

    protected $table = 'movie_media';
    public $timestamps = false;
    /** @var list<string> */
    protected $fillable = ['movie_id', 'kind', 'url', 'meta', 'source_slug', 'crawled_at'];

    /** @return BelongsTo<Movie, $this> */
    public function movie(): BelongsTo
    {
        return $this->belongsTo(Movie::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['movie_id' => 'integer', 'meta' => 'array', 'crawled_at' => 'datetime'];
    }
}
