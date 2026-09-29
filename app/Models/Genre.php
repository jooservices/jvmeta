<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\GenreFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Genre extends Model
{
    /** @use HasFactory<GenreFactory> */
    use HasFactory;

    protected $table = 'genres';
    public $timestamps = false;
    /** @var list<string> */
    protected $fillable = ['label_normalized', 'label_raw'];

    /** @return BelongsToMany<Movie, $this> */
    public function movies(): BelongsToMany
    {
        return $this->belongsToMany(Movie::class, 'movie_genres')->withPivot(['source_slug', 'crawled_at']);
    }
}
