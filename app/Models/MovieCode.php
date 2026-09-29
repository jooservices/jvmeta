<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\MovieCodeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MovieCode extends Model
{
    /** @use HasFactory<MovieCodeFactory> */
    use HasFactory;

    public const KIND_DVD = 'dvd';
    public const KIND_UNCENSORED = 'uncensored';
    public const KIND_FC2 = 'fc2';
    public const KIND_RE_RELEASE = 're_release';
    public const KIND_LEAK = 'leak';
    public const KIND_BOX_SET = 'box_set';

    protected $table = 'movie_codes';
    public $timestamps = false;
    /** @var list<string> */
    protected $fillable = ['movie_id', 'code', 'code_normalized', 'kind', 'source_slug', 'source_url', 'crawled_at'];

    /** @return BelongsTo<Movie, $this> */
    public function movie(): BelongsTo
    {
        return $this->belongsTo(Movie::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['movie_id' => 'integer', 'crawled_at' => 'datetime'];
    }
}
