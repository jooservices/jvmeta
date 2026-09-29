<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MovieCredit extends Model
{
    public const ROLE_DIRECTOR = 'director';

    protected $table = 'movie_credits';

    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = [
        'movie_id', 'role', 'name', 'site_slug', 'crawled_at',
    ];

    /** @return BelongsTo<Movie, $this> */
    public function movie(): BelongsTo
    {
        return $this->belongsTo(Movie::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'movie_id' => 'integer',
            'crawled_at' => 'datetime',
        ];
    }
}
