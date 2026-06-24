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
