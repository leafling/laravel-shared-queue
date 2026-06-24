# Implementation Plan: Shared Database Queue & Scoped Import Tracking Package

This plan proposes creating a reusable, utilitarian package **`leafling/laravel-shared-queue`**. The package will handle dynamic subdomain/host context serialization and site-scoped background import job tracking, enabling multiple separate Laravel applications to share a database safely.

---

## Package Architecture (`leafling/laravel-shared-queue`)

### 1. Dynamic Host Context Serialization
Encapsulates capturing the scheme, host, and port of the active HTTP request when a job is constructed, and restoring them inside the CLI queue worker before the job executes.

### 2. Automated Site-Scoped Import Tracking
Provides a generic, database-backed background/import job tracking mechanism (`import_jobs` table) that:
* **Auto-populates** `site_code` on record creation based on current configuration or the queue identifier.
* **Auto-scopes** all queries via a Global Scope so each site/domain only sees its own background tasks without manual query filtering.

---

## Proposed Changes

### Component 1: New Utilitarian Package (`leafling/laravel-shared-queue`)

We will initialize the package in `/Volumes/data/www/packages/leafling/laravel-shared-queue` with the following structure:

#### [NEW] [composer.json](file:///Volumes/data/www/packages/leafling/laravel-shared-queue/composer.json)
```json
{
    "name": "leafling/laravel-shared-queue",
    "description": "Utilitarian package for sharing queue databases and scoping background tasks across multiple Laravel applications and domains.",
    "type": "library",
    "license": "MIT",
    "autoload": {
        "psr-4": {
            "Leafling\\SharedQueue\\": "src/"
        }
    },
    "require": {
        "php": ">=8.3",
        "illuminate/support": "^11.0|^12.0|^13.0",
        "illuminate/database": "^11.0|^12.0|^13.0",
        "illuminate/queue": "^11.0|^12.0|^13.0"
    },
    "extra": {
        "laravel": {
            "providers": [
                "Leafling\\SharedQueue\\SharedQueueServiceProvider"
            ]
        }
    }
}
```

#### [NEW] [SharedQueueServiceProvider.php](file:///Volumes/data/www/packages/leafling/laravel-shared-queue/src/SharedQueueServiceProvider.php)
Registers migrations and configuration automatically.
```php
<?php

namespace Leafling\SharedQueue;

use Illuminate\Support\ServiceProvider;

class SharedQueueServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        }
    }
}
```

#### [NEW] [2026_06_24_000001_create_shared_import_jobs_table.php](file:///Volumes/data/www/packages/leafling/laravel-shared-queue/database/migrations/2026_06_24_000001_create_shared_import_jobs_table.php)
The migration file bundled with the package.
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('site_code')->nullable()->index();
            $table->string('type')->default('default');
            $table->string('status')->default('pending');
            $table->unsignedInteger('current_step')->default(0);
            $table->unsignedInteger('total_steps')->default(1);
            $table->text('message')->nullable();
            $table->json('step_details')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_jobs');
    }
};
```

#### [NEW] [ImportJob.php](file:///Volumes/data/www/packages/leafling/laravel-shared-queue/src/Models/ImportJob.php)
Handles auto-population and auto-scoping of `site_code`.
```php
<?php

namespace Leafling\SharedQueue\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class ImportJob extends Model
{
    protected $table = 'import_jobs';

    protected $fillable = [
        'site_code',
        'type',
        'status',
        'current_step',
        'total_steps',
        'message',
        'step_details',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'step_details' => 'array',
    ];

    protected static function booted(): void
    {
        // Auto-populate site_code on create
        static::creating(function ($model) {
            $model->site_code = $model->site_code ?? config('shared-queue.site_code', env('QUEUE_NAME', 'admintools'));
        });

        // Auto-scope all queries to the active site_code
        static::addGlobalScope('site', function (Builder $builder) {
            $siteCode = config('shared-queue.site_code', env('QUEUE_NAME', 'admintools'));
            $builder->where('site_code', $siteCode);
        });
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', ['pending', 'running'])
            ->where('updated_at', '>=', Carbon::now()->subMinutes(15));
    }

    public function isStale(): bool
    {
        return in_array($this->status, ['pending', 'running']) &&
            $this->updated_at->lt(Carbon::now()->subMinutes(15));
    }
}
```

#### [NEW] [SerializesHostContext.php](file:///Volumes/data/www/packages/leafling/laravel-shared-queue/src/Traits/SerializesHostContext.php)
```php
<?php

namespace Leafling\SharedQueue\Traits;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\URL;

trait SerializesHostContext
{
    public ?string $dispatchedHost = null;

    protected function captureHostContext(): void
    {
        if (app()->runningInConsole() === false) {
            $this->dispatchedHost = request()->getHost();
        }
    }

    protected function restoreHostContext(): void
    {
        if ($this->dispatchedHost) {
            $scheme = request()->secure() ? 'https://' : 'http://';
            $fullUrl = $scheme . $this->dispatchedHost;

            Config::set('app.url', $fullUrl);
            URL::forceRootUrl($fullUrl);
        }
    }
}
```

---

### Component 2: Integration in `org.mnstatefair.admintools`

#### [MODIFY] [composer.json](file:///Volumes/data/www/apps/org.mnstatefair.admintools/composer.json)
Enable path-based repository link and require the package.
```json
  "repositories": [
    {
      "type": "path",
      "url": "../../packages/leafling/laravel-shared-queue",
      "options": {
        "symlink": true
      }
    },
    ...
  ],
  "require": {
    ...
    "leafling/laravel-shared-queue": "*",
    ...
  }
```

#### [MODIFY] [app/Http/Controllers/Admin/Momentus/ExhibitorsController.php](file:///Volumes/data/www/apps/org.mnstatefair.admintools/app/Http/Controllers/Admin/Momentus/ExhibitorsController.php)
Import the package's `ImportJob` class instead of the local one:
```diff
-use App\Models\ImportJob;
+use Leafling\SharedQueue\Models\ImportJob;
```

#### [DELETE] Local files in `org.mnstatefair.admintools`:
* `app/Models/ImportJob.php`
* Existing migrations that created the `import_jobs` table locally, since the package now defines it.

---

## Verification Plan

### Automated Tests
* Run `composer update leafling/laravel-shared-queue` to install.
* Execute database migrations (`php artisan migrate`).
* Run the existing application tests to ensure no regression.

### Manual Verification
* Dispatch an import task from `admintools` and check the database; verify `site_code` is automatically set to `admintools`.
* Check that queries on `ImportJob` are automatically restricted to `site_code = admintools`.
