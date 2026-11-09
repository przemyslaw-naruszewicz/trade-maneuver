<?php

declare(strict_types=1);

// The demo analytics application connects like any analytics application: with its own identifier and its own
// RabbitMQ producer user, to the message broker that RABBITMQ_HOST, RABBITMQ_PORT and RABBITMQ_VHOST name.
return [
    // The identifier sent as `source`; it also routes the application's outcome queue, tm.signal-outcomes.<source>.
    'source' => (string) env('DEMO_ANALYTICS_SOURCE', 'demo-analytics'),
    // The investment configuration that trade signals address unless --investment-configuration-ref says otherwise.
    'investment_configuration_ref' => (string) env('DEMO_ANALYTICS_INVESTMENT_CONFIGURATION_REF', ''),
    'rabbitmq' => [
        'host' => (string) env('RABBITMQ_HOST', 'rabbitmq'),
        'port' => (int) env('RABBITMQ_PORT', 5672),
        'vhost' => (string) env('RABBITMQ_VHOST', 'trade-maneuver'),
        'user' => (string) env('DEMO_ANALYTICS_RABBITMQ_USER', 'demo-analytics'),
        'password' => (string) env('DEMO_ANALYTICS_RABBITMQ_PASSWORD', ''),
    ],
];
