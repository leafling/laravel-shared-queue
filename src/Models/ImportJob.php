<?php

namespace Leafling\SharedQueue\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class ImportJob extends Model
{
    protected $table = 'import_jobs';

    public function getConnectionName()
    {
        return config('shared-queue.connection', parent::getConnectionName());
    }

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

    public static function resolveSiteCode(): string
    {
        // 1. Explicit configuration has highest priority
        if ($siteCode = config('shared-queue.site_code')) {
            return $siteCode;
        }

        // 2. If running in HTTP context, use the request host
        if (!app()->runningInConsole()) {
            return request()->getHost();
        }

        // 3. If running in CLI worker/console, extract from restored app.url
        if ($appUrl = config('app.url')) {
            $host = parse_url($appUrl, PHP_URL_HOST);
            if ($host && $host !== 'localhost' && $host !== '127.0.0.1') {
                return $host;
            }
        }

        // 4. Fallback to queue name or default
        return env('QUEUE_NAME', 'default');
    }

    protected static function booted(): void
    {
        // Auto-populate site_code on create
        static::creating(function ($model) {
            $model->site_code = $model->site_code ?? static::resolveSiteCode();
        });

        // Auto-scope all queries to the active site_code
        static::addGlobalScope('site', function (Builder $builder) {
            $builder->where('site_code', static::resolveSiteCode());
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
