<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

/** Enqueue listing seeds for one site (or all) then optionally dispatch. */
final class CrawlSourceCommand extends Command
{
    protected $signature = 'crawl:source {slug?} {--dispatch : Also run crawl:dispatch after tick} {--limit=20}';

    protected $description = 'Enqueue crawl listings for one source slug (or all when omitted).';

    public function handle(): int
    {
        $slug = $this->argument('slug');
        $limit = max(1, (int) $this->option('limit'));

        $params = ['--limit' => $limit];
        if (is_string($slug) && $slug !== '') {
            $params['--source'] = $slug;
        }

        $code = $this->call('crawl:tick', $params);
        if ($code !== self::SUCCESS) {
            return $code;
        }

        if ($this->option('dispatch')) {
            return $this->call('crawl:dispatch', ['--limit' => $limit]);
        }

        return self::SUCCESS;
    }
}
