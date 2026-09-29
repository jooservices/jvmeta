<?php

declare(strict_types=1);

namespace App\Logging;

use App\Observability\OpenObserveClient;
use App\Observability\TelemetrySanitizer;
use Monolog\Logger;

final class CreateOpenObserveLogger
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __invoke(array $config): Logger
    {
        $handler = new OpenObserveLogHandler(
            app(OpenObserveClient::class),
            app(TelemetrySanitizer::class),
            $config['level'] ?? 'debug',
        );

        return new Logger('openobserve', [$handler]);
    }
}
