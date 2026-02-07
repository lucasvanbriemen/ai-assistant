<?php

namespace App\Providers;

use App\AI\Services\AIService;
use Illuminate\Support\ServiceProvider;

class AIServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(function () {
        });

        $this->app->singleton(AIService::class, function () {
            return new AIService($this->app->make());
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
