<?php

declare(strict_types=1);

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

if (! app()->runningUnitTests()) {
    Schedule::command('crawl:tick')->everyMinute();
    Schedule::command('crawl:dispatch')->everyMinute();
    Schedule::command('jvmeta:obs-publish-metrics')->everyMinute();
    Schedule::command('jvmeta:watchdog')->everyFiveMinutes();
}
