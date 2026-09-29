<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CrawlRun extends Model
{
    protected $table = 'crawl_runs';
    public $timestamps = false;
    /** @var list<string> */
    protected $fillable = ['source_slug', 'started_at', 'finished_at', 'status', 'pages_fetched', 'movies_new', 'movies_updated', 'failures', 'proxy_requests', 'proxy_bytes', 'browser_fetches'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'finished_at' => 'datetime', 'pages_fetched' => 'integer', 'movies_new' => 'integer', 'movies_updated' => 'integer', 'failures' => 'integer', 'proxy_requests' => 'integer', 'proxy_bytes' => 'integer', 'browser_fetches' => 'integer'];
    }
}
