<?php

return [
    'name' => 'FLYNK Connector',
    'description' => 'Verortet Container an Organisations-Knoten und pusht sie via FLYNK REST API',
    'version' => '1.0.0',

    'scope_type' => 'parent',

    'routing' => [
        'prefix' => 'flynk-connector',
        'middleware' => ['web', 'auth'],
    ],

    'guard' => 'web',

    'navigation' => [
        'route' => 'flynk-connector.dashboard',
        'icon'  => 'heroicon-o-arrows-right-left',
        'order' => 60,
    ],
];
