<?php

namespace App\Providers;

use App\AI\Core\PluginList;
use App\AI\Plugins\EmailPlugin;
use App\AI\Services\AIService;
use Illuminate\Support\ServiceProvider;

class AIServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(PluginList::class, function () {
            $plugins = new PluginList();

            // Register all plugins
            $plugins->add(new EmailPlugin());

            return $plugins;
        });

        $this->app->singleton(AIService::class, function () {
            return new AIService($this->app->make(PluginList::class));
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
