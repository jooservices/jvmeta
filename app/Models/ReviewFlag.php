<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReviewFlag extends Model
{
    protected $table = 'review_flags';
    public $timestamps = false;
    /** @var list<string> */
    protected $fillable = ['movie_id', 'code_normalized', 'source_slug', 'consecutive_failures', 'reason', 'flagged_at', 'resolved_at'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['movie_id' => 'integer', 'consecutive_failures' => 'integer', 'flagged_at' => 'datetime', 'resolved_at' => 'datetime'];
    }
}
