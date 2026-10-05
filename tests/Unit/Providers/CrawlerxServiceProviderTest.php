<?php

declare(strict_types=1);

namespace Tests\Unit\Providers;

use App\Services\Crawler\LaravelConfigLoginCookieProvider;
use JOOservices\CrawlerX\CrawlerXFactory;
use ReflectionClass;
use ReflectionProperty;
use Tests\TestCase;

final class CrawlerxServiceProviderTest extends TestCase
{
    public function test_boot_wires_login_cookies_and_keeps_sessions_in_the_worker(): void
    {
        self::assertInstanceOf(LaravelConfigLoginCookieProvider::class, $this->factoryProperty('loginCookieProvider'));

        $factory = new ReflectionClass(CrawlerXFactory::class);

        if (! $factory->hasProperty('sessionCache')) {
            return;
        }

        self::assertNull($factory->getProperty('sessionCache')->getValue());
    }

    private function factoryProperty(string $name): mixed
    {
        return (new ReflectionProperty(CrawlerXFactory::class, $name))->getValue();
    }
}
