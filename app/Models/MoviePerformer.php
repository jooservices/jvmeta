<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\MoviePerformerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MoviePerformer extends Model
{
    /** @use HasFactory<MoviePerformerFactory> */
    use HasFactory;

    protected $table = 'movie_performers';
    public $incrementing = false;
    public $timestamps = false;
    /** @var list<string> */
    protected $fillable = ['movie_id', 'performer_id', 'source_slug', 'crawled_at'];

    /** @return BelongsTo<Movie, $this> */
    public function movie(): BelongsTo
    {
        return $this->belongsTo(Movie::class);
    }

    /** @return BelongsTo<Performer, $this> */
    public function performer(): BelongsTo
    {
        return $this->belongsTo(Performer::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['movie_id' => 'integer', 'performer_id' => 'integer', 'crawled_at' => 'datetime'];
    }
}
