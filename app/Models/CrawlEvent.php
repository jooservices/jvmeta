<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CrawlEvent extends Model
{
    public const KIND_BLOCKED = 'blocked';
    public const KIND_CHALLENGE = 'challenge';
    public const KIND_SOFT404 = 'soft404';
    public const KIND_RATE_LIMITED = 'rate_limited';
    public const KIND_DOMAIN_CHANGE = 'domain_change';
    public const KIND_PROXY_EXHAUSTED = 'proxy_exhausted';
    public const KIND_PARSE_DRIFT = 'parse_drift';

    protected $table = 'crawl_events';
    public $timestamps = false;
    /** @var list<string> */
    protected $fillable = ['source_slug', 'kind', 'url', 'detail', 'created_at'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['detail' => 'array', 'created_at' => 'datetime'];
    }
}
