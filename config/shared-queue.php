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
        'auth:admin',
    ],

    /*
    |--------------------------------------------------------------------------
    | Auth Guards
    |--------------------------------------------------------------------------
    |
    | Guards inspected sequentially to determine the initiator user when a
    | job tracker record is created during a web request.
    |
    */
    'auth_guards' => ['admin', 'web'],

    /*
    |--------------------------------------------------------------------------
    | Reset Authorization Gate
    |--------------------------------------------------------------------------
    |
    | Optional Laravel Gate or policy permission string to check before resetting
    | a job status (e.g. 'manage-shared-queue'). Set to null to rely solely on
    | route middleware.
    |
    */
    'gate' => null,

    /*
    |--------------------------------------------------------------------------
    | Fallback Site Code
    |--------------------------------------------------------------------------
    |
    | Fallback site code used when SHARED_QUEUE_SITE_CODE is not set, running in
    | CLI console, and app.url host is unavailable.
    |
    */
    'fallback_site_code' => env('SHARED_QUEUE_SITE_CODE_FALLBACK', 'default'),

    /*
    |--------------------------------------------------------------------------
    | Markdown Renderer Callback
    |--------------------------------------------------------------------------
    |
    | Optional custom Markdown rendering callback or class method for status
    | messages. If null, messages are safely HTML-escaped by default.
    |
    */
    'markdown_renderer' => null,

    /*
    |--------------------------------------------------------------------------
    | Watcher Polling Settings
    |--------------------------------------------------------------------------
    |
    | Configuration options for the AJAX polling component <x-shared-queue-watcher>.
    | Automatic backoff dynamically increases poll delay when status is unchanged,
    | reducing server load on long-running tasks.
    |
    */
    'poll_min_interval' => 2000,   // Minimum / initial poll delay in milliseconds
    'poll_max_interval' => 15000,  // Maximum poll delay cap in milliseconds
    'poll_auto_backoff' => true,   // Enable dynamic automatic exponential backoff
];
