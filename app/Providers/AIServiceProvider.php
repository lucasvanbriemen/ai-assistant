<?php

namespace App\Providers;

use App\AI\Core\PluginRegistry;
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
