<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\TurnoServiceInterface;
use App\Services\TurnoService;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(TurnoServiceInterface::class, TurnoService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
