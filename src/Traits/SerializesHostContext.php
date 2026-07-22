<?php

namespace Leafling\SharedQueue\Traits;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\URL;

trait SerializesHostContext
{
    public ?string $dispatchedHost = null;
    public ?string $dispatchedScheme = null;

    protected function captureHostContext(): void
    {
        if (app()->runningInConsole() === false) {
            $this->dispatchedHost = request()->getHost();
            $this->dispatchedScheme = request()->getScheme();
        }
    }

    protected function restoreHostContext(): void
    {
        if ($this->dispatchedHost) {
            // Sanitize host to prevent host header injection
            $cleanHost = preg_replace('/[^a-zA-Z0-9\.\-]/', '', $this->dispatchedHost);
            if (empty($cleanHost)) {
                return;
            }

            $scheme = $this->dispatchedScheme ? rtrim($this->dispatchedScheme, ':/') . '://' : 'https://';
            $fullUrl = $scheme . $cleanHost;

            Config::set('app.url', $fullUrl);
            URL::forceRootUrl($fullUrl);
        }
    }
}
