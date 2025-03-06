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
use App\Services\Interface\AuditoriaServiceInterface;
use App\Services\Implementation\AuditoriaService;
use App\Services\Interface\DeporteServiceInterface;
use App\Services\Implementation\DeporteService;
use App\Services\Interface\EquipoServiceInterface;
use App\Services\Implementation\EquipoService;
use App\Services\Interface\TorneoServiceInterface;
use App\Services\Implementation\TorneoService;
use App\Services\Interface\JugadorServiceInterface;
use App\Services\Implementation\JugadorService;
use App\Services\Interface\ZonaServiceInterface;
use App\Services\Implementation\ZonaService;
use App\Services\Interface\FechaServiceInterface;
use App\Services\Implementation\FechaService;
use App\Services\Interface\PartidoServiceInterface;
use App\Services\Implementation\PartidoService;

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
        $this->app->bind(AuditoriaServiceInterface::class, AuditoriaService::class);
        $this->app->bind(DeporteServiceInterface::class, DeporteService::class);
        $this->app->bind(EquipoServiceInterface::class, EquipoService::class);
        $this->app->bind(TorneoServiceInterface::class, TorneoService::class);
        $this->app->bind(JugadorServiceInterface::class, JugadorService::class);
        $this->app->bind(ZonaServiceInterface::class, ZonaService::class);
        $this->app->bind(FechaServiceInterface::class, FechaService::class);
        $this->app->bind(PartidoServiceInterface::class, PartidoService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
