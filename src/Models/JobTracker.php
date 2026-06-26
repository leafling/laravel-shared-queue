<?php

namespace Leafling\SharedQueue\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class JobTracker extends Model
{
    protected $table = 'jobs_tracker';

    public function getConnectionName(): ?string
    {
        return config('shared-queue.connection', parent::getConnectionName());
    }

    protected $fillable = [
        'site_code',
        'initiated_by',
        'user_id',
        'user_type',
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

    /**
     * Get the polymorphic user that initiated the job.
     */
    public function user(): \Illuminate\Database\Eloquent\Relations\MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Safely resolve the morph relation class only if it exists in the active codebase.
     */
    public function getInitiatorRelationAttribute()
    {
        if ($this->user_type && class_exists($this->user_type)) {
            return $this->user;
        }

        return null;
    }

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
        // Auto-populate site_code and initiator on create
        static::creating(function (self $model): void {
            $model->site_code = $model->site_code ?? static::resolveSiteCode();

            // Dynamic session initiator resolving
            if (!app()->runningInConsole() && !$model->initiated_by) {
                $user = null;
                foreach (['admin', 'web'] as $guard) {
                    if (auth($guard)->check()) {
                        $user = auth($guard)->user();
                        break;
                    }
                }

                if ($user) {
                    $model->initiated_by = class_basename($user) . ' #' . $user->getKey() . 
                                           ($user->name ? " ({$user->name})" : '');
                    $model->user_id = $user->getKey();
                    $model->user_type = get_class($user);
                } else {
                    $model->initiated_by = 'Guest';
                }
            }
        });

        // Auto-scope all queries to the active site_code
        static::addGlobalScope('site', function (Builder $builder): void {
            $builder->where('site_code', static::resolveSiteCode());
        });
    }

    public function scopeActive(Builder $query): Builder
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
