<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\AI\AIManager;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(AIManager::class, fn () => new AIManager());
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
