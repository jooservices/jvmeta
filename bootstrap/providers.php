<?php

declare(strict_types=1);

use App\Providers\AppServiceProvider;
use App\Providers\CrawlerxServiceProvider;
use App\Providers\DependencyServiceProvider;

return [
    AppServiceProvider::class,
    CrawlerxServiceProvider::class,
    DependencyServiceProvider::class,
];
