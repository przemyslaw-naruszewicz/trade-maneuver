<?php

declare(strict_types=1);

// The registry of known analytics applications, supplied at deployment as a comma-separated list of entries
// `<source>:<RabbitMQ user>`: the identifier each one sends as `source` and the RabbitMQ user it publishes as, for
// example TRADING_ANALYTICS_APPLICATIONS=demo-analytics:demo-analytics,demo-research:demo-research-publisher.
$analyticsApplications = [];
foreach (explode(',', (string) env('TRADING_ANALYTICS_APPLICATIONS', '')) as $entry) {
    if (trim($entry) === '') {
        continue;
    }
    [$identifier, $rabbitMqUser] = array_pad(explode(':', $entry, 2), 2, '');
    $analyticsApplications[] = ['id' => trim($identifier), 'rabbitmq_user' => trim($rabbitMqUser)];
}

return [
    'analytics_applications' => $analyticsApplications,
    // The connection of Trade Maneuver's own RabbitMQ user; docker/rabbitmq holds the topology and the users.
    'rabbitmq' => [
        'host' => (string) env('RABBITMQ_HOST', 'rabbitmq'),
        'port' => (int) env('RABBITMQ_PORT', 5672),
        'vhost' => (string) env('RABBITMQ_VHOST', 'trade-maneuver'),
        'user' => (string) env('RABBITMQ_USER', 'trade-maneuver'),
        'password' => (string) env('RABBITMQ_PASSWORD', ''),
    ],
    // The catalogue of the simulated broker: fictional instruments on the fictional exchange DEMO, which has the MIC
    // format but is not a registered MIC. A symbol outside it is unknown to the simulated broker.
    'simulated_broker' => [
        'instruments' => [
            ['symbol' => 'DEMO_A', 'exchange' => 'DEMO', 'quote_currency' => 'EUR', 'available' => true],
            ['symbol' => 'DEMO_G', 'exchange' => 'DEMO', 'quote_currency' => 'USD', 'available' => true],
            ['symbol' => 'DEMO_H', 'exchange' => 'DEMO', 'quote_currency' => 'EUR', 'available' => false],
        ],
    ],
];
