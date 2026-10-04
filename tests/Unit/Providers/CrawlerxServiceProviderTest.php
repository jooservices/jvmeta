<?php

declare(strict_types=1);

namespace Tests\Unit\Providers;

use App\Services\Crawler\LaravelConfigLoginCookieProvider;
use JOOservices\CrawlerX\CrawlerXFactory;
use Psr\SimpleCache\CacheInterface;
use ReflectionProperty;
use Tests\TestCase;

final class CrawlerxServiceProviderTest extends TestCase
{
    public function test_boot_configures_crawlerx_with_cache_and_login_cookies(): void
    {
        self::assertInstanceOf(CacheInterface::class, $this->factoryProperty('sessionCache'));
        self::assertInstanceOf(LaravelConfigLoginCookieProvider::class, $this->factoryProperty('loginCookieProvider'));
    }

    private function factoryProperty(string $name): mixed
    {
        return (new ReflectionProperty(CrawlerXFactory::class, $name))->getValue();
    }
}
