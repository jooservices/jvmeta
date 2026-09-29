<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\MovieObservationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MovieObservation extends Model
{
    /** @use HasFactory<MovieObservationFactory> */
    use HasFactory;

    protected $table = 'movie_observations';
    public $timestamps = false;
    /** @var list<string> */
    protected $fillable = ['movie_id', 'field', 'value', 'value_hash', 'source_slug', 'source_url', 'crawled_at', 'is_primary'];

    /** @return BelongsTo<Movie, $this> */
    public function movie(): BelongsTo
    {
        return $this->belongsTo(Movie::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['movie_id' => 'integer', 'crawled_at' => 'datetime', 'is_primary' => 'boolean'];
    }
}
