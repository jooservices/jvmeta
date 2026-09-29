<?php

declare(strict_types=1);

namespace App\Services\Normalize\Normalizers;

/**
 * Normalizer for the shared-catalog adapter family (Fc2, JavDb, Duga, Heyzo,
 * TokyoHot, CaribbeanCom). Those adapters expose the detail page's dl/dt
 * fields as raw metadata{} keys (English and Japanese labels), so the key map
 * is anchored on the catalog label set while still honouring per-source
 * config overrides.
 */
final class CatalogSourceNormalizer extends DefaultSourceNormalizer
{
    /** @var list<string> */
    private const SLUGS = ['fc2', 'javdb', 'duga', 'heyzo', 'tokyohot', 'caribbeancom'];

    public function supports(string $slug): bool
    {
        return in_array($slug, self::SLUGS, true);
    }

    /**
     * @return array<string, list<string>>
     */
    protected function keyMap(string $slug): array
    {
        return array_merge(parent::keyMap($slug), [
            'maker' => ['Maker', 'メーカー', 'Studio'],
            'label' => ['Label', 'レーベル'],
            'series' => ['Series', 'シリーズ'],
            'score' => ['Rating', '評価', 'Score'],
            'genres' => ['Genre', 'ジャンル', 'Categories'],
            'magnets' => ['download_url', 'downloads'],
        ]);
    }
}
