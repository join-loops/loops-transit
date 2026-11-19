<?php

$hosts = fn ($value): array => array_values(array_filter(array_map(
    fn ($host) => strtolower(trim($host)),
    explode(',', (string) $value)
)));

return [

    'url' => env('TRANSIT_URL', env('APP_URL', 'http://localhost')),

    'operator' => [
        'name' => env('TRANSIT_OPERATOR_NAME', env('APP_NAME', 'Loops Transit')),
        'contact' => env('TRANSIT_OPERATOR_CONTACT'),
        'url' => env('TRANSIT_OPERATOR_URL'),
    ],

    'require_advertised' => (bool) env('TRANSIT_REQUIRE_ADVERTISED', true),

    'allowed_hosts' => $hosts(env('TRANSIT_ALLOWED_HOSTS')),

    'blocked_hosts' => $hosts(env('TRANSIT_BLOCKED_HOSTS')),

    'clock_skew' => (int) env('TRANSIT_CLOCK_SKEW', 300),

    'max_intent_days' => (int) env('TRANSIT_MAX_INTENT_DAYS', 30),

    'max_jws_bytes' => (int) env('TRANSIT_MAX_JWS_BYTES', 16384),

    'max_receipts' => (int) env('TRANSIT_MAX_RECEIPTS', 10),

    'rate_limits' => [
        'ip' => (int) env('TRANSIT_RATE_LIMIT_IP', 60),
        'host' => (int) env('TRANSIT_RATE_LIMIT_HOST', 120),
    ],

    'prune_after_days' => (int) env('TRANSIT_PRUNE_AFTER_DAYS', 90),

    'http' => [
        'timeout' => (int) env('TRANSIT_HTTP_TIMEOUT', 10),
        'max_bytes' => (int) env('TRANSIT_HTTP_MAX_BYTES', 1048576),
        'allow_private_networks' => (bool) env('TRANSIT_ALLOW_PRIVATE_NETWORKS', false),
    ],

];
