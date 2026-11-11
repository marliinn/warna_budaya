<?php

return [
    'enabled' => (bool) env('ANALYTICS_ENABLED', false),
    'cookie_name' => env('ANALYTICS_COOKIE_NAME', 'warna_budaya_visitor'),
    'cookie_days' => (int) env('ANALYTICS_COOKIE_DAYS', 365),
    'retention_days' => (int) env('ANALYTICS_RETENTION_DAYS', 90),
    'dedupe_seconds' => (int) env('ANALYTICS_DEDUPE_SECONDS', 5),
    'max_range_days' => (int) env('ANALYTICS_MAX_RANGE_DAYS', 90),
];
