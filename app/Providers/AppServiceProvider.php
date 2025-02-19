<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\TurnoServiceInterface;
use App\Services\TurnoService;
use App\Services\Interface\CanchaServiceInterface;
use App\Services\Implementation\CanchaService;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(TurnoServiceInterface::class, TurnoService::class);
        $this->app->bind(CanchaServiceInterface::class, CanchaService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
