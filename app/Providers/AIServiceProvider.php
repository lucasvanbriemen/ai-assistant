<?php

namespace App\Providers;

use App\AI\Core\PluginRegistry;
use App\AI\Plugins\EmailPlugin;
use App\AI\Plugins\CalendarPlugin;
use App\AI\Services\AIService;
use Illuminate\Support\ServiceProvider;

class AIServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(PluginRegistry::class, function () {
            $registry = new PluginRegistry();

            // Register all plugins
            $registry->register(new EmailPlugin());
            $registry->register(new CalendarPlugin());

            return $registry;
        });

        $this->app->singleton(AIService::class, function () {
            return new AIService($this->app->make(PluginRegistry::class));
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
