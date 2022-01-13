<?php

namespace Pranesh\PrettyRoutes;

use Illuminate\Support\ServiceProvider;
use Pranesh\PrettyRoutes\Commands\PrettyRoutesCommand;

class PrettyRoutesServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     */
    public function boot()
    {

        if ($this->app->runningInConsole()) {

            $this->commands([
                PrettyRoutesCommand::class,
            ]);
        }
    }

    /**
     * Register the application services.
     */
    public function register()
    {
        // Register the main class to use with the facade
        $this->app->singleton('pretty-routes', function () {
            return new PrettyRoutes;
        });
    }
}
