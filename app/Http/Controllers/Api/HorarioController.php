<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\Interface\HorarioServiceInterface;


class HorarioController extends Controller
{
    protected $horarioService;

    public function __construct(HorarioServiceInterface $horarioService)
    {
        $this->horarioService = $horarioService;
    }

    public function index()
    {
        $user = Auth::user();
        abort_unless($user->tokenCan('horarios:show') || $user->rol === 'admin', 403, 'No tienes permisos para realizar esta acción');
        
        return $this->horarioService->getHorarios();
    }

    public function show($id)
    {
        $user = Auth::user();
        abort_unless($user->tokenCan('horarios:showOne') || $user->rol === 'admin', 403, 'No tienes permisos para realizar esta acción');
        
        return $this->horarioService->showHorario($id);
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        abort_unless($user->tokenCan('horarios:create') || $user->rol === 'admin', 403, 'No tienes permisos para realizar esta acción');
        
        return $this->horarioService->storeHorario($request);
    }

    public function destroy($id)
    {
        $user = Auth::user();
        abort_unless($user->tokenCan('horarios:delete') || $user->rol === 'admin', 403, 'No tienes permisos para realizar esta acción');
        
        return $this->horarioService->deleteHorario($id);
    }

    public function getHorariosPorDiaSemana(Request $request)
    {
        $user = Auth::user();
        abort_unless($user->tokenCan('horarios:fecha') || $user->rol === 'admin', 403, 'No tienes permisos para realizar esta acción');
        
        return $this->horarioService->getHorariosPorDiaSemana($request);
    }

    public function deshabilitarFranjaHoraria(Request $request)
    {
        $user = Auth::user();
        abort_unless($user->tokenCan('horarios:indisponibilizar') || $user->rol === 'admin', 403, 'No tienes permisos para realizar esta acción');
        
        return $this->horarioService->deshabilitarFranjaHoraria($request);
    }

    public function habilitarFranjaHoraria(Request $request)
    {
        $user = Auth::user();
        abort_unless($user->tokenCan('horarios:habilitar') || $user->rol === 'admin', 403, 'No tienes permisos para realizar esta acción');
        
        return $this->horarioService->habilitarFranjaHoraria($request);
    }

    public function showFranjasHorariasNoDisponibles()
    {
        $user = Auth::user();
        abort_unless($user->tokenCan('horariosNoDisponible:show') || $user->rol === 'admin', 403, 'No tienes permisos para realizar esta acción');
        
        return $this->horarioService->showFranjasHorariasNoDisponibles();
    }

    public function getHorariosExtremosActivos()
    {
        $user = Auth::user();
        abort_unless($user->tokenCan('horarios:show') || $user->rol === 'admin', 403, 'No tienes permisos para realizar esta acción');
        
        return $this->horarioService->getHorariosExtremosActivos();
    }
}