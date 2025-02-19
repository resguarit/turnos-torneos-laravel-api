<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\Interface\TurnoServiceInterface;
use App\Services\Implementation\TurnoService;
use App\Services\Interface\CanchaServiceInterface;
use App\Services\Implementation\CanchaService;
use App\Services\Interface\HorarioServiceInterface;
use App\Services\Implementation\HorarioService;
use App\Services\Interface\DisponibilidadServiceInterface;
use App\Services\Implementation\DisponibilidadService;
use App\Services\Interface\BloqueoTemporalServiceInterface;
use App\Services\Implementation\BloqueoTemporalService;
use App\Services\Interface\ConfigServiceInterface;
use App\Services\Implementation\ConfigService;



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
        $this->app->bind(DisponibilidadServiceInterface::class, DisponibilidadService::class);
        $this->app->bind(BloqueoTemporalServiceInterface::class, BloqueoTemporalService::class);
        $this->app->bind(ConfigServiceInterface::class, ConfigService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
