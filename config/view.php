<?php

return [
    'paths' => [
        resource_path('views'),
    ],

    // Keep this as a path string instead of realpath(): fresh deployments may
    // not have created storage/framework/views yet when `artisan optimize` runs.
    'compiled' => env(
        'VIEW_COMPILED_PATH',
        storage_path('framework/views')
    ),
];
