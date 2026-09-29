<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ApiKeyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApiKey extends Model
{
    /** @use HasFactory<ApiKeyFactory> */
    use HasFactory;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_REVOKED = 'revoked';

    protected $table = 'api_keys';
    public $timestamps = false;
    /** @var list<string> */
    protected $fillable = ['prefix', 'key_hash', 'label', 'status', 'revoked_at', 'abuse_rpm', 'created_at'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['revoked_at' => 'datetime', 'abuse_rpm' => 'integer', 'created_at' => 'datetime'];
    }
}
