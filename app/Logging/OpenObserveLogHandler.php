<?php

declare(strict_types=1);

namespace App\Logging;

use App\Observability\OpenObserveClient;
use App\Observability\TelemetrySanitizer;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;
use Throwable;

final class OpenObserveLogHandler extends AbstractProcessingHandler
{
    public function __construct(
        private readonly OpenObserveClient $client,
        private readonly TelemetrySanitizer $sanitizer,
        int|string|Level $level = Level::Debug,
        bool $bubble = true,
    ) {
        parent::__construct($level, $bubble);
    }

    protected function write(LogRecord $record): void
    {
        if (! $this->client->enabled()) {
            return;
        }

        try {
            $this->client->ingestLogs([[
                '_timestamp' => (int) ((float) $record->datetime->format('U.u') * 1_000_000),
                'level' => $record->level->getName(),
                'message' => $record->message,
                'channel' => $record->channel,
                'context' => $this->sanitizer->sanitizeContext($record->context),
                'extra' => $this->sanitizer->sanitizeContext($record->extra),
            ]]);
        } catch (Throwable) {
            // Fail-open: never break the app logger.
        }
    }
}
