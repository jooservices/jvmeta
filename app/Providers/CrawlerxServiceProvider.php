<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Crawler\LaravelConfigLoginCookieProvider;
use App\Services\Crawler\SourceLoginCookieStore;
use Illuminate\Support\ServiceProvider;
use JOOservices\CrawlerX\CrawlerXFactory;

/**
 * Wires the login cookies kept in laravel-config.
 *
 * Fetch sessions stay in the worker process. This provider does not pass a cache.
 */
final class CrawlerxServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        CrawlerXFactory::configure(
            logins: new LaravelConfigLoginCookieProvider(fn(): SourceLoginCookieStore => $this->app->make(SourceLoginCookieStore::class)),
        );
    }
}
