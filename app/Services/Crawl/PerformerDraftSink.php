<?php

declare(strict_types=1);

namespace App\Services\Crawl;

use App\Data\Crawl\PerformerDraft;

interface PerformerDraftSink
{
    public function accept(PerformerDraft $draft): void;
}
