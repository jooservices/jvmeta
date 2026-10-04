<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Dependencies\Dependency;
use App\Services\Dependencies\DependencyMonitor;
use App\Services\Dependencies\Probes\ElasticsearchProbe;
use App\Services\Dependencies\Probes\EmbedderProbe;
use App\Services\Dependencies\Probes\MongoProbe;
use App\Services\Dependencies\Probes\ObservabilityProbe;
use App\Services\Dependencies\Probes\PostgresProbe;
use App\Services\Dependencies\Probes\RedisProbe;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

final class DependencyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(DependencyMonitor::class, function (Application $app): DependencyMonitor {
            return new DependencyMonitor([
                Dependency::Postgres->value => $app->make(PostgresProbe::class),
                Dependency::Redis->value => $app->make(RedisProbe::class),
                Dependency::Elasticsearch->value => $app->make(ElasticsearchProbe::class),
                Dependency::Mongo->value => $app->make(MongoProbe::class),
                Dependency::Embedder->value => $app->make(EmbedderProbe::class),
                Dependency::Observability->value => $app->make(ObservabilityProbe::class),
            ]);
        });
    }
}
