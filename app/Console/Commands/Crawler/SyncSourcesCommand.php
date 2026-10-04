<?php

declare(strict_types=1);

namespace App\Console\Commands\Crawler;

use App\Models\Source;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;

final class SyncSourcesCommand extends Command
{
    protected $signature = 'crawler:sync-sources';

    protected $description = 'Synchronize configured JVMeta sources.';

    public function handle(): int
    {
        $sources = config('jvmeta_sources.sources', []);
        if (! is_array($sources) || $sources === []) {
            return self::SUCCESS;
        }

        foreach ($sources as $slug => $source) {
            if (! is_string($slug) || ! is_array($source)) {
                continue;
            }

            $defaultGap = (float) Arr::get($source, 'gap_seconds_default', config('jvmeta_sources.defaults.fallback_gap_seconds.default', 30));
            $existing = Source::query()->find($slug);
            Source::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => (string) Arr::get($source, 'name'),
                    'base_url' => (string) Arr::get($source, 'base_url'),
                    'enabled' => (bool) Arr::get($source, 'enabled', config('jvmeta_sources.defaults.enabled', true)),
                    'priority' => (int) Arr::get($source, 'priority'),
                    'needs_proxy' => (bool) Arr::get($source, 'needs_proxy', config('jvmeta_sources.defaults.needs_proxy', false)),
                    'gap_seconds_default' => $defaultGap,
                    'gap_seconds_min' => (float) Arr::get($source, 'gap_seconds_min', config('jvmeta_sources.defaults.fallback_gap_seconds.min', 30)),
                    'gap_seconds_max' => (float) Arr::get($source, 'gap_seconds_max', config('jvmeta_sources.defaults.fallback_gap_seconds.max', 120)),
                    'gap_seconds_current' => $existing instanceof Source ? $existing->gap_seconds_current : $defaultGap,
                    'consecutive_failures' => $existing instanceof Source ? $existing->consecutive_failures : 0,
                    'circuit_state' => $existing instanceof Source ? $existing->circuit_state : Source::CIRCUIT_CLOSED,
                    'circuit_opened_at' => $existing instanceof Source ? $existing->circuit_opened_at : null,
                    'last_success_at' => $existing instanceof Source ? $existing->last_success_at : null,
                    'last_error_at' => $existing instanceof Source ? $existing->last_error_at : null,
                    'last_error' => $existing instanceof Source ? $existing->last_error : null,
                    'soft404_markers' => $existing instanceof Source ? $existing->soft404_markers : null,
                ],
            );
        }

        return self::SUCCESS;
    }
}
