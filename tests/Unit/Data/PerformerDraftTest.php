<?php

declare(strict_types=1);

namespace Tests\Unit\Data;

use App\Data\Crawl\PerformerDraft;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class PerformerDraftTest extends TestCase
{
    public function test_creates_readonly_performer_draft(): void
    {
        $draft = PerformerDraft::from(
            sourceSlug: 'javdb',
            externalId: 'actor-1',
            nameRomaji: 'Aoi Example',
            nameKanji: '青い例',
            aliases: ['Example Aoi'],
            profileUrl: 'https://example.test/actors/actor-1',
        );

        self::assertSame('javdb', $draft->sourceSlug);
        self::assertSame('actor-1', $draft->externalId);
        self::assertSame(['Example Aoi'], $draft->aliases);
        self::assertSame('https://example.test/actors/actor-1', $draft->profileUrl);
    }

    public function test_rejects_empty_external_id(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new PerformerDraft('javdb', '');
    }

    public function test_rejects_empty_alias(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new PerformerDraft('javdb', 'actor-1', aliases: ['']);
    }
}
