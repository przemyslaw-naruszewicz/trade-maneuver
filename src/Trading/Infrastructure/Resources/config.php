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
];
