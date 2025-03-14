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
use App\Services\Interface\DashboardServiceInterface;
use App\Services\Implementation\DashboardService;
use App\Services\Interface\UserServiceInterface;
use App\Services\Implementation\UserService;
use App\Services\Interface\AuthServiceInterface;
use App\Services\Implementation\AuthService;
use App\Services\Interface\PersonaServiceInterface;
use App\Services\Implementation\PersonaService;
use App\Services\Interface\CuentaCorrienteServiceInterface;
use App\Services\Implementation\CuentaCorrienteService;
use App\Services\Interface\TransaccionServiceInterface;
use App\Services\Implementation\TransaccionService;

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
        $this->app->bind(UserServiceInterface::class, UserService::class);
        $this->app->bind(AuthServiceInterface::class, AuthService::class);
        $this->app->bind(BloqueoTemporalServiceInterface::class, BloqueoTemporalService::class);
        $this->app->bind(ConfigServiceInterface::class, ConfigService::class);
        $this->app->bind(DashboardServiceInterface::class, DashboardService::class);
        $this->app->bind(PersonaServiceInterface::class, PersonaService::class);
        $this->app->bind(CuentaCorrienteServiceInterface::class, CuentaCorrienteService::class);
        $this->app->bind(TransaccionServiceInterface::class, TransaccionService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
