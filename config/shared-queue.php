<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Shared Queue Site Code
    |--------------------------------------------------------------------------
    |
    | This value is used as the site identifier for scoping background tasks.
    | If null, the package will dynamically resolve it using the HTTP request
    | host or the queue worker's restored URL host.
    |
    */
    'site_code' => env('SHARED_QUEUE_SITE_CODE'),

    /*
    |--------------------------------------------------------------------------
    | Database Connection
    |--------------------------------------------------------------------------
    |
    | The database connection to use when querying/saving the tracking jobs.
    | Set to null to use the default connection.
    |
    */
    'connection' => env('SHARED_QUEUE_CONNECTION', null),

    /*
    |--------------------------------------------------------------------------
    | Route Path / Prefix
    |--------------------------------------------------------------------------
    |
    | The prefix path where the dashboard and job status routes will be registered.
    | Set to false to disable package route registration entirely.
    |
    */
    'path' => env('SHARED_QUEUE_PATH', 'admin/shared-queue'),

    /*
    |--------------------------------------------------------------------------
    | Route Middleware
    |--------------------------------------------------------------------------
    |
    | The list of middleware applied to the package routes.
    |
    */
    'middleware' => [
        'web',
        // 'auth:admin',
    ],
];
