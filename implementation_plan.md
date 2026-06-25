# Implementation Plan: Configurable Dashboard & Progress Watcher for `leafling/laravel-shared-queue`

This plan details how to add a progress watcher component, a customizable configuration file, and an admin dashboard view to the `leafling/laravel-shared-queue` package. It introduces full path and middleware flexibility via a config file, and resolves paths dynamically in Javascript using Laravel's named routes.

## Proposed Changes

### Component 1: Customizable Configuration

#### [NEW] [config/shared-queue.php](file:///Volumes/data/www/packages/leafling/laravel-shared-queue/config/shared-queue.php)
Allows customizing the dashboard route path, middleware stack, or disabling dashboard routes altogether.
```php
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
```

---

### Component 2: Service Provider Update

#### [MODIFY] [src/SharedQueueServiceProvider.php](file:///Volumes/data/www/packages/leafling/laravel-shared-queue/src/SharedQueueServiceProvider.php)
Updates the service provider to merge the config, load views, support publishing, register dynamic routes based on config, and register the Blade component.
```php
<?php

namespace Leafling\SharedQueue;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Blade;

class SharedQueueServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/shared-queue.php', 'shared-queue');
    }

    public function boot(): void
    {
        // 1. Load Package Migrations
        if ($this->app->runningInConsole()) {
            $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        }

        // 2. Load Package Views (accessible via 'shared-queue::')
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'shared-queue');

        // 3. Register Publishable Assets (allows overriding package views and config)
        $this->publishes([
            __DIR__ . '/../resources/views' => resource_path('views/vendor/shared-queue'),
        ], 'shared-queue-views');

        $this->publishes([
            __DIR__ . '/../config/shared-queue.php' => config_path('shared-queue.php'),
        ], 'shared-queue-config');

        // 4. Register Package Routes
        $this->registerRoutes();

        // 5. Register Reusable Blade Component
        Blade::component('shared-queue::components.watcher', 'shared-queue-watcher');
    }

    protected function registerRoutes(): void
    {
        $path = config('shared-queue.path', 'admin/shared-queue');
        if ($path === false) {
            return;
        }

        Route::middleware(config('shared-queue.middleware', ['web']))
            ->prefix($path)
            ->name('shared-queue.')
            ->group(function () {
                Route::get('/dashboard', [Http\Controllers\SharedQueueController::class, 'dashboard'])->name('dashboard');
                Route::get('/jobs/{job}/status', [Http\Controllers\SharedQueueController::class, 'status'])->name('status');
                Route::post('/jobs/{job}/reset', [Http\Controllers\SharedQueueController::class, 'reset'])->name('reset');
            });
    }
}
```

---

### Component 3: Package Controller & Routes

#### [NEW] [src/Http/Controllers/SharedQueueController.php](file:///Volumes/data/www/packages/leafling/laravel-shared-queue/src/Http/Controllers/SharedQueueController.php)
Handles fetching the jobs listing, outputting status, and resetting stuck active jobs.
```php
<?php

namespace Leafling\SharedQueue\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Leafling\SharedQueue\Models\ImportJob;

class SharedQueueController extends Controller
{
    /**
     * Display a dashboard listing all import jobs.
     */
    public function dashboard()
    {
        $jobs = ImportJob::latest()->paginate(25);
        return view('shared-queue::dashboard', compact('jobs'));
    }

    /**
     * Get the JSON status of a specific job.
     */
    public function status(ImportJob $job)
    {
        return response()->json([
            'id' => $job->id,
            'status' => $job->status,
            'current_step' => $job->current_step,
            'total_steps' => $job->total_steps,
            'message' => $job->message,
            'step_details' => $job->step_details,
            'updated_at' => $job->updated_at->toIso8601String(),
        ]);
    }

    /**
     * Manually reset/force-fail a stuck active job.
     */
    public function reset(ImportJob $job)
    {
        if (in_array($job->status, ['pending', 'running'])) {
            $job->update([
                'status' => 'failed',
                'message' => 'Job was manually reset by an administrator.',
            ]);
            return redirect()->back()->with('message', 'Job status was reset successfully.');
        }

        return redirect()->back()->with('errorMessage', 'Job is not active.');
    }
}
```

---

### Component 4: Package Views

#### [NEW] [resources/views/components/watcher.blade.php](file:///Volumes/data/www/packages/leafling/laravel-shared-queue/resources/views/components/watcher.blade.php)
Watcher layout. Utilizes `{{ route('shared-queue.status', $job->id) }}` dynamically to make API calls to whatever prefix is specified in configuration.
```html
@if($job && in_array($job->status, ['pending', 'running']))
    <div id="shared-queue-watcher-{{ $job->id }}" class="shared-queue-watcher-container" style="margin: 15px 0; padding: 15px; border: 1px solid #ddd; border-radius: 6px; background: #fafafa;">
        <div style="font-weight: 600; margin-bottom: 8px;">
            Import Status: <span class="progress-status-label">{{ ucfirst($job->status) }}</span>
        </div>
        
        <div class="progress-bar-wrapper" style="background: #e5e7eb; border-radius: 9999px; overflow: hidden; height: 16px; margin-bottom: 8px;">
            <div class="progress-bar-fill" style="width: {{ $job->total_steps > 0 ? ($job->current_step / $job->total_steps * 100) : 0 }}%; background: #3b82f6; height: 100%; transition: width 0.4s ease;"></div>
        </div>
        
        <div class="progress-status-message" style="font-size: 0.875rem; color: #4b5563;">
            {{ $job->message ?? 'Initializing...' }}
        </div>
        
        <script>
            (function() {
                const jobId = "{{ $job->id }}";
                const container = document.getElementById(`shared-queue-watcher-${jobId}`);
                const fill = container.querySelector('.progress-bar-fill');
                const label = container.querySelector('.progress-status-label');
                const message = container.querySelector('.progress-status-message');
                
                const pollInterval = setInterval(async () => {
                    try {
                        const response = await fetch("{{ route('shared-queue.status', $job->id) }}");
                        const data = await response.json();
                        
                        // Update progress bar width
                        const percent = data.total_steps > 0 ? (data.current_step / data.total_steps * 100) : 0;
                        fill.style.width = `${percent}%`;
                        
                        // Update messages
                        label.textContent = data.status.charAt(0).toUpperCase() + data.status.slice(1);
                        message.textContent = data.message || 'Processing...';
                        
                        if (['completed', 'failed'].includes(data.status)) {
                            clearInterval(pollInterval);
                            setTimeout(() => {
                                window.location.reload();
                            }, 1000);
                        }
                    } catch (e) {
                        console.error('Failed to poll shared queue job status:', e);
                    }
                }, 2000);
            })();
        </script>
    </div>
@endif
```

#### [NEW] [resources/views/dashboard.blade.php](file:///Volumes/data/www/packages/leafling/laravel-shared-queue/resources/views/dashboard.blade.php)
Default minimal admin interface listing the scoped import records with a reset button.
```html
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shared Queue Dashboard</title>
    <style>
        body { font-family: -apple-system, sans-serif; padding: 40px; background: #f3f4f6; color: #1f2937; }
        .card { background: white; padding: 24px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        h1 { margin-top: 0; font-size: 1.5rem; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { text-align: left; padding: 12px; border-bottom: 1px solid #e5e7eb; }
        th { background: #f9fafb; font-weight: 600; }
        .badge { display: inline-block; padding: 4px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: 600; }
        .badge-running { background: #dbeafe; color: #1e40af; }
        .badge-completed { background: #dcfce7; color: #166534; }
        .badge-failed { background: #fee2e2; color: #991b1b; }
        .badge-pending { background: #fef9c3; color: #854d0e; }
        button { background: #ef4444; color: white; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer; }
        button:hover { background: #dc2626; }
    </style>
</head>
<body>
    <div class="card">
        <h1>Shared Queue Jobs</h1>
        
        @if(session('message'))
            <p style="color: green;">{{ session('message') }}</p>
        @endif
        
        @if(session('errorMessage'))
            <p style="color: red;">{{ session('errorMessage') }}</p>
        @endif
        
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Type</th>
                    <th>Status</th>
                    <th>Progress</th>
                    <th>Last Message</th>
                    <th>Created At</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($jobs as $job)
                    <tr>
                        <td>#{{ $job->id }}</td>
                        <td>{{ $job->type }}</td>
                        <td>
                            <span class="badge badge-{{ $job->status }}">
                                {{ $job->status }}
                            </span>
                        </td>
                        <td>{{ $job->current_step }} / {{ $job->total_steps }}</td>
                        <td>{{ $job->message }}</td>
                        <td>{{ $job->created_at->format('Y-m-d H:i:s') }}</td>
                        <td>
                            @if(in_array($job->status, ['pending', 'running']))
                                <form action="{{ route('shared-queue.reset', $job) }}" method="POST" style="display:inline;">
                                    @csrf
                                    <button type="submit">Reset</button>
                                </form>
                            @else
                                -
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7">No background jobs tracked.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        
        <div style="margin-top: 20px;">
            {{ $jobs->links() }}
        </div>
    </div>
</body>
</html>
```

## Verification Plan

### Automated Verification
- Verify file compilation and php linting on all modified/new files.

### Manual Verification
- Test that the route registers at custom prefix paths when customized in configuration.
