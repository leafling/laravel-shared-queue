# Laravel Shared Queue & Scoped Task Tracker (`leafling/laravel-shared-queue`)

This package provides utility features to safely share a single database queue and task tracking system across multiple separate Laravel applications, domains, or subdomains.

## Features

1. **Queue Isolation Helpers:** Best practices and configuration settings to segment workers using distinct queue names on a shared `jobs_queue` table (avoiding class serialization crashes).
2. **Subdomain/Host Context Serialization:** A trait (`SerializesHostContext`) that automatically captures the active request scheme/domain/port when dispatching queue jobs and restores it inside the CLI background worker context.
3. **Automated Site-Scoped Task Tracking:** An `ImportJob` Eloquent model and migration that automatically scopes and filters task status tracking by a dynamically resolved domain or manually specified `site_code` using global query scopes.
4. **Configurable Admin Dashboard:** A built-in dashboard listing site-scoped jobs with failure reset capabilities. Routes and path prefix are fully customizable or can be completely disabled.
5. **Dynamic Progress Watcher:** A self-contained Blade component (`<x-shared-queue-watcher>`) that polls and renders live job progress updates using vanilla JavaScript.
6. **Reusable Dashboard Partial:** A self-contained Blade view partial (`shared-queue::partials.jobs-table`) that can be embedded into any custom layout or page in your application.

---

## Installation

### 1. Configure Local Composer Repository (For Development)
To install the package locally from your `/Volumes/data/www/packages` folder, update each application's `composer.json` with a path-based repository (or use the GitHub VCS repository link):

```json
"repositories": [
    {
        "type": "path",
        "url": "../../packages/leafling/laravel-shared-queue",
        "options": {
            "symlink": true
        }
    }
],
"require": {
    "leafling/laravel-shared-queue": "*"
}
```

Then run:
```bash
composer update leafling/laravel-shared-queue
```

### 2. Run Database Migrations
Run the package migrations to create the shared `import_jobs` table:
```bash
php artisan migrate
```

---

## Configuration

To customize route prefixes, middleware, database connection, or default site codes, publish the package configuration file:

```bash
php artisan vendor:publish --tag=shared-queue-config
```

This creates `config/shared-queue.php` with the following configuration options:

```php
return [
    // Scopes all query/create operations on ImportJob.
    // If null, it dynamically resolves via HTTP request host (e.g. competition.mnstatefair.org)
    // or the queue worker's restored URL host.
    'site_code' => env('SHARED_QUEUE_SITE_CODE', null),

    // Connection name to write import tracking jobs to.
    // Set to null to use your default connection.
    'connection' => env('SHARED_QUEUE_CONNECTION', null),

    // The route path/prefix where the admin dashboard and status endpoints are registered.
    // Set to false to disable package route registration entirely on this site.
    'path' => env('SHARED_QUEUE_PATH', 'admin/shared-queue'),

    // Middlewares applied to the package dashboard/watcher routes.
    'middleware' => [
        'web',
        // 'auth:admin',
    ],
];
```

---

## Usage

### 1. Serializing Subdomain Host Context
Add the `SerializesHostContext` trait to queue jobs that need to know what subdomain/domain triggered them (e.g., for generating correct links in notification emails):

```php
<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Leafling\SharedQueue\Traits\SerializesHostContext;

class SendDynamicEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, SerializesHostContext;

    public function __construct()
    {
        // 1. Capture the request host (e.g. competition.mnstatefair.org) on dispatch
        $this->captureHostContext();
    }

    public function handle()
    {
        // 2. Restore URL and config context inside the CLI process
        $this->restoreHostContext();

        // Any generated URLs or links will now correctly point to competition.mnstatefair.org
        // Mail::to(...)->send(...);
    }
}
```

### 2. Scoped Import Task Tracking
Use the package's `ImportJob` model to track import progress. Operations are automatically scoped to the active domain (e.g. `competition.mnstatefair.org`):

```php
use Leafling\SharedQueue\Models\ImportJob;

// 1. Create a tracking record (site_code is auto-populated to active request host)
$tracker = ImportJob::create([
    'type' => 'momentus-exhibitors',
    'status' => 'pending',
    'current_step' => 0,
    'total_steps' => 4,
]);

// 2. Fetch active tracking jobs (Automatically scopes query by current host)
$activeJob = ImportJob::active()->first();

// 3. Update job steps in background job handler
$tracker->update([
    'current_step' => 1,
    'message' => 'Imported exhibitors successfully.',
]);
```

### 3. Displaying live progress (Watcher Component)
Embed the watcher Blade component in your views to show real-time progress of a running job. The component handles AJAX polling automatically using the configured routes:

```html
<!-- Inside any Blade template where a job is running -->
<x-shared-queue-watcher :job="$runningJob" />
```

### 4. Admin Dashboard Customization & Embedding
You can access the built-in dashboard at `/admin/shared-queue/dashboard` (or whatever custom path you specified in config). 

#### Publishing and Customizing Dashboard Views
To customize or restyle the views, publish them to your local project:
```bash
php artisan vendor:publish --tag=shared-queue-views
```
This copies the views to `resources/views/vendor/shared-queue/`.

#### Embedding in Layouts
If you want to render the tracking list inside your own admin layout rather than a standalone page, include the self-contained dashboard partial:

```html
@extends('layouts.admin')

@section('content')
    <div class="card">
        <h2>System Background Tasks</h2>
        @include('shared-queue::partials.jobs-table', ['jobs' => $jobs])
    </div>
@endsection
```

### 5. Accessing All Jobs (Admins)
To query import jobs across all sites bypassing the dynamic global scope, use standard Eloquent scope bypassing:

```php
// Retrieve all records regardless of site_code/domain
$allJobs = ImportJob::withoutGlobalScope('site')->get();
```

---

## Production Worker (systemd) Setup

Deploy one background worker daemon per codebase pointing to its isolated queue name:

**`/etc/systemd/system/laravel-worker-admintools.service`:**
```ini
[Service]
WorkingDirectory=/var/www/http/apps/org.mnstatefair.admintools
ExecStart=/usr/bin/php artisan queue:work database --queue=admintools --sleep=3 --tries=3
Restart=always
```

**`/etc/systemd/system/laravel-worker-register.service`:**
```ini
[Service]
WorkingDirectory=/var/www/http/apps/org.mnstatefair.register
ExecStart=/usr/bin/php artisan queue:work database --queue=register --sleep=3 --tries=3
Restart=always
```
