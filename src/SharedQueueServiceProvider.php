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
