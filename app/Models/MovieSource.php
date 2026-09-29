<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MovieSource extends Model
{
    protected $table = 'movie_sources';

    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = [
        'movie_id', 'site_slug', 'mongo_id', 'source_url', 'source_code', 'crawled_at',
    ];

    /** @return BelongsTo<Movie, $this> */
    public function movie(): BelongsTo
    {
        return $this->belongsTo(Movie::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['crawled_at' => 'datetime'];
    }
}
