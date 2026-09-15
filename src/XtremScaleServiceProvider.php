<?php

namespace CosmicVibes\XtremScale;

use Illuminate\Support\ServiceProvider;

class XtremScaleServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/xtrem-scale.php', 'xtrem-scale'
        );

        $this->app->singleton('xtrem-scale', function ($app) {
            return new XtremScale(
                config('xtrem-scale.ip_address'),
                config('xtrem-scale.send_port', 4445),
                config('xtrem-scale.receive_port', 5556),
                config('xtrem-scale.timeout', 5)
            );
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/xtrem-scale.php' => config_path('xtrem-scale.php'),
            ], 'xtrem-scale-config');
        }
    }
}
