<?php

namespace Dabergut\Idempotency;

use Illuminate\Support\ServiceProvider;

class IdempotencyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/idempotency.php', 'idempotency');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/idempotency.php' => config_path('idempotency.php'),
            ], 'idempotency-config');
        }

        $this->app['router']->aliasMiddleware('idempotent', EnsureIdempotency::class);
    }
}
