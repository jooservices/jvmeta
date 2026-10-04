<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Crawler\LaravelConfigLoginCookieProvider;
use App\Services\Crawler\SourceLoginCookieStore;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\ServiceProvider;
use JOOservices\CrawlerX\CrawlerXFactory;

/**
 * Wires crawlerx's shared session store (the configured Laravel cache store)
 * and the login cookies kept in laravel-config.
 */
final class CrawlerxServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        CrawlerXFactory::configure(
            cache: Cache::store(),
            logins: new LaravelConfigLoginCookieProvider(fn(): SourceLoginCookieStore => $this->app->make(SourceLoginCookieStore::class)),
        );
    }
}
