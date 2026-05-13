<?php

declare(strict_types=1);

return [
    'session_path' => env('ALGORIX_SESSION_PATH', storage_path('app/algorix-pay/userbot.madeline')),

    'api' => [
        'id' => env('ALGORIX_API_ID'),
        'hash' => env('ALGORIX_API_HASH'),
    ],

    'drivers' => [
        'click' => [
            'enabled' => env('ALGORIX_CLICK_ENABLED', true),
            'source' => env('ALGORIX_CLICK_SOURCE', 'clickuz'),
            'class' => \AlgorixPay\Drivers\ClickDriver::class,
        ],
        'payme' => [
            'enabled' => env('ALGORIX_PAYME_ENABLED', false),
            'source' => env('ALGORIX_PAYME_SOURCE', 'payme'),
            'class' => \AlgorixPay\Drivers\PaymeDriver::class,
        ],
        'uzum' => [
            'enabled' => env('ALGORIX_UZUM_ENABLED', false),
            'source' => env('ALGORIX_UZUM_SOURCE', 'uzumbank_bot'),
            'class' => \AlgorixPay\Drivers\UzumDriver::class,
        ],
    ],

    'dedup' => [
        'ttl_seconds' => env('ALGORIX_DEDUP_TTL', 10),
        'cache_store' => env('ALGORIX_DEDUP_CACHE', null),
    ],

    'matcher' => [
        'enabled' => env('ALGORIX_MATCHER_ENABLED', true),
        'cache_store' => env('ALGORIX_MATCHER_CACHE', null),
        'default_ttl' => (int) env('ALGORIX_MATCHER_TTL', 900),
        'key_prefix' => env('ALGORIX_MATCHER_PREFIX', 'algorix:pending:'),
        'default_currency' => env('ALGORIX_MATCHER_CURRENCY', 'UZS'),
        'tail_mode' => env('ALGORIX_MATCHER_TAIL_MODE', 'tiyin'),
        'tail_generators' => [
            'tiyin' => \AlgorixPay\Matcher\Tail\TiyinTailGenerator::class,
            'sum' => \AlgorixPay\Matcher\Tail\SumTailGenerator::class,
        ],
        'tail_max_attempts' => (int) env('ALGORIX_MATCHER_MAX_ATTEMPTS', 50),
        'currency_mismatch_action' => env('ALGORIX_MATCHER_CURRENCY_MISMATCH', 'log'),
    ],

    'logging' => [
        'channel' => env('ALGORIX_LOG_CHANNEL', 'stack'),
    ],
];
