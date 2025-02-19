<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\TurnoServiceInterface;
use App\Services\TurnoService;
use App\Services\Interface\CanchaServiceInterface;
use App\Services\Implementation\CanchaService;
use App\Services\Interface\HorarioServiceInterface;
use App\Services\Implementation\HorarioService;


class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(TurnoServiceInterface::class, TurnoService::class);
        $this->app->bind(CanchaServiceInterface::class, CanchaService::class);
        $this->app->bind(HorarioServiceInterface::class, HorarioService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
