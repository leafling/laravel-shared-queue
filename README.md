# Laravel Shared Queue & Scoped Task Tracker (`leafling/laravel-shared-queue`)

This package provides utility features to safely share a single database queue and task tracking system across multiple separate Laravel applications, domains, or subdomains.

## Features

1. **Queue Isolation Helpers:** Best practices and configuration settings to segment workers using distinct queue names on a shared `jobs_queue` table (avoiding class serialization crashes).
2. **Subdomain/Host Context Serialization:** A trait (`SerializesHostContext`) that automatically captures the active request scheme/domain/port when dispatching queue jobs and restores it inside the CLI background worker context.
3. **Automated Site-Scoped Task Tracking:** A pre-packaged `ImportJob` model and migration that automatically scopes and filters task status tracking by a configurable `site_code` using Eloquent global scopes.

---

## Installation

### 1. Configure Local Composer Repository (For Development)
To install the package locally from your `/Volumes/data/www/packages` folder, update each application's `composer.json` with a path-based repository:

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
Run migrations to create the shared `import_jobs` table:
```bash
php artisan migrate
```

---

## Configuration

### Queue Name Isolation
Configure distinct queue names in each application's `.env` and `config/queue.php` to prevent workers from picking up and failing on classes from other codebases.

**`config/queue.php`:**
```php
'database' => [
    'driver' => 'database',
    'table' => 'jobs_queue',
    'queue' => env('QUEUE_NAME', 'default'),
    'retry_after' => 900,
],
```

**`admintools/.env`:**
```env
QUEUE_CONNECTION=database
QUEUE_NAME=admintools
```

**`register/.env`:**
```env
QUEUE_CONNECTION=database
QUEUE_NAME=register
```

---

## Usage

### 1. Serializing Subdomain Host Context
Add the `SerializesHostContext` trait to queue jobs that need to know what subdomain/domain triggered them (e.g. for generating correct links in notification emails):

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
        // 1. Capture the request host (e.g. livestock.mnstatefair.org) on dispatch
        $this->captureHostContext();
    }

    public function handle()
    {
        // 2. Restore URL and config context in the CLI process
        $this->restoreHostContext();

        // Any generated URLs will now correctly point to livestock.mnstatefair.org
        // Mail::to(...)->send(...);
    }
}
```

### 2. Scoped Import Task Tracking
Use the package's `ImportJob` model to track import progress. Operations are automatically scoped to the current site code (`env('QUEUE_NAME')` or `config('app.site_code')`):

```php
use Leafling\SharedQueue\Models\ImportJob;

// 1. Create a tracking record (site_code is auto-populated)
$tracker = ImportJob::create([
    'type' => 'momentus-exhibitors',
    'status' => 'pending',
    'current_step' => 0,
    'total_steps' => 4,
]);

// 2. Fetch active tracking jobs (Automatically scopes query by active site_code)
$activeJob = ImportJob::active()->first();

// 3. Update job steps in background job handler
$tracker->update([
    'current_step' => 1,
    'message' => 'Imported exhibitors successfully.',
]);
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
