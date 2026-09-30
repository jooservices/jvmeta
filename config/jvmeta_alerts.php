<?php

declare(strict_types=1);

return [
    /*
    | Master on/off switch. When false, AlertDispatcher skips every alert
    | (no fan-out to Telegram / activity log). Default: on.
    */
    'enabled' => (bool) env('JVMETA_ALERTS_ENABLED', true),

    /*
    | Telegram credentials prefer jooservices/laravel-notifications config /
    | NOTIFICATION_TELEGRAM_* env, with TELEGRAM_* (below) as legacy fallback.
    */
    'telegram' => [
        'bot_token' => env('TELEGRAM_BOT_TOKEN', ''),
        'chat_id' => env('TELEGRAM_CHAT_ID', ''),
        'api_base' => env('TELEGRAM_API_BASE', 'https://api.telegram.org'),
        'timeout' => (int) env('TELEGRAM_TIMEOUT', 5),
    ],
    'debounce_seconds' => (int) env('JVMETA_ALERT_DEBOUNCE_SECONDS', 900),
    'miss_debounce_seconds' => (int) env('JVMETA_MISS_ALERT_DEBOUNCE_SECONDS', 3600),
    'title_failure_threshold' => (int) env('JVMETA_TITLE_FAILURE_THRESHOLD', 3),
    'watchdog' => [
        'heartbeat_stale_seconds' => (int) env('JVMETA_WATCHDOG_HEARTBEAT_STALE_SECONDS', 600),
        'source_stale_seconds' => (int) env('JVMETA_WATCHDOG_SOURCE_STALE_SECONDS', 86400),
    ],
    'default_soft404_markers' => [
        'not found',
        'page not found',
        '404',
        'ページが見つかりません',
        'ページが存在しません',
        'お探しのページは見つかりませんでした',
        '找不到页面',
        '找不到頁面',
    ],
];
