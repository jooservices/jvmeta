<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApiUsageLog extends Model
{
    protected $table = 'api_usage_log';
    public $timestamps = false;
    /** @var list<string> */
    protected $fillable = ['api_key_id', 'endpoint', 'method', 'status_code', 'response_ms', 'ip_hash', 'created_at'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['api_key_id' => 'integer', 'status_code' => 'integer', 'response_ms' => 'integer', 'created_at' => 'datetime'];
    }
}
