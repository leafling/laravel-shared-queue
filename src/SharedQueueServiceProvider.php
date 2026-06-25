<?php

namespace Leafling\SharedQueue;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Blade;
use Leafling\SharedQueue\Http\Controllers\SharedQueueController;

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
                Route::get('/', [SharedQueueController::class, 'dashboard'])->name('dashboard');
                Route::get('/jobs/{job}/status', [SharedQueueController::class, 'status'])->name('status');
                Route::post('/jobs/{job}/reset', [SharedQueueController::class, 'reset'])->name('reset');
            });
    }
}
