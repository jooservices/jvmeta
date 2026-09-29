<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\CrawlQueueFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CrawlQueue extends Model
{
    /** @use HasFactory<CrawlQueueFactory> */
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_CLAIMED = 'claimed';
    public const STATUS_DONE = 'done';
    public const STATUS_FAILED = 'failed';
    public const STATUS_SKIPPED = 'skipped';
    public const KIND_LISTING = 'listing';
    public const KIND_DETAIL = 'detail';
    public const KIND_PERFORMER_LISTING = 'performer_listing';
    public const KIND_PERFORMER_DETAIL = 'performer_detail';
    public const KIND_GALLERY = 'gallery';

    protected $table = 'crawl_queue';
    public $timestamps = false;
    /** @var list<string> */
    protected $fillable = ['source_slug', 'url', 'kind', 'status', 'attempts', 'max_attempts', 'next_attempt_at', 'claimed_at', 'locked_by', 'last_error'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['attempts' => 'integer', 'max_attempts' => 'integer', 'next_attempt_at' => 'datetime', 'claimed_at' => 'datetime'];
    }
}
