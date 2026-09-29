<?php

declare(strict_types=1);

namespace App\Services\Crawl;

use App\Models\Source;

/**
 * Detects soft-404 pages (HTTP success / parse blob that means "gone") using
 * per-source markers plus defaults from config.
 */
final class Soft404Detector
{
    public function matches(?string $haystack, Source|string|null $source = null): bool
    {
        if ($haystack === null || trim($haystack) === '') {
            return false;
        }

        $normalized = mb_strtolower($haystack);
        foreach ($this->markers($source) as $marker) {
            if ($marker !== '' && str_contains($normalized, mb_strtolower($marker))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function markers(Source|string|null $source): array
    {
        $defaults = config('jvmeta_alerts.default_soft404_markers', []);
        $defaults = is_array($defaults) ? array_values(array_filter($defaults, 'is_string')) : [];

        $model = $source instanceof Source
            ? $source
            : (is_string($source) ? Source::query()->find($source) : null);

        $rawMarkers = $model instanceof Source ? $model->getAttribute('soft404_markers') : null;
        $custom = [];
        if (is_array($rawMarkers)) {
            foreach ($rawMarkers as $marker) {
                if (is_string($marker) && $marker !== '') {
                    $custom[] = $marker;
                }
            }
        }

        return array_values(array_unique([...$defaults, ...$custom]));
    }
}
