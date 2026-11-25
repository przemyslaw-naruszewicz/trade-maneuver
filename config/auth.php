<?php

return [

    'defaults' => [
        'guard' => 'web',
    ],

    // The single operator logs in through the session guard. The Trading context registers the provider driver,
    // which reads the operator account. There is no password broker: only console commands reset the password.
    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'operators',
        ],
    ],

    'providers' => [
        'operators' => [
            'driver' => 'trading-operators',
        ],
    ],

];
