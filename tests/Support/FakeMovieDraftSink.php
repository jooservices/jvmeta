<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Data\Crawl\MovieDraft;
use App\Services\Crawl\MovieDraftSink;

final class FakeMovieDraftSink implements MovieDraftSink
{
    /** @var list<MovieDraft> */
    public array $drafts = [];

    public function accept(MovieDraft $draft): void
    {
        $this->drafts[] = $draft;
    }
}
