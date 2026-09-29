<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Contracts\AlertNotifier;

final class CompositeAlertNotifier implements AlertNotifier
{
    /** @param list<AlertNotifier> $notifiers */
    public function __construct(private readonly array $notifiers) {}

    public function notify(string $subject, string $body, array $context = []): void
    {
        foreach ($this->notifiers as $notifier) {
            $notifier->notify($subject, $body, $context);
        }
    }
}
