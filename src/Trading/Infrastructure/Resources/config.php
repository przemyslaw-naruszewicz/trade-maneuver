<?php

declare(strict_types=1);

// The registry of known analytics applications, supplied at deployment as a comma-separated list of the
// identifiers they send as `source`, for example TRADING_ANALYTICS_APPLICATIONS=demo-analytics,demo-research.
$analyticsApplications = [];
foreach (explode(',', (string) env('TRADING_ANALYTICS_APPLICATIONS', '')) as $identifier) {
    if (trim($identifier) !== '') {
        $analyticsApplications[] = ['id' => trim($identifier)];
    }
}

return [
    'analytics_applications' => $analyticsApplications,
];
