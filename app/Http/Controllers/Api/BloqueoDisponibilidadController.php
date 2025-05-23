<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Interface\BloqueoDisponibilidadServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\BloqueoDisponibilidadTurno;
class BloqueoDisponibilidadController extends Controller
{
    protected $bloqueoDisponibilidadService;

    public function __construct(BloqueoDisponibilidadServiceInterface $bloqueoDisponibilidadService)
    {
        $this->bloqueoDisponibilidadService = $bloqueoDisponibilidadService;
    }

    public function bloquearDisponibilidad(Request $request)
    {
        return $this->bloqueoDisponibilidadService->bloquearDisponibilidad($request);
    }

    public function desbloquearDisponibilidad(Request $request)
    { 
        return $this->bloqueoDisponibilidadService->desbloquearDisponibilidad($request);
    }

    public function getAll()
    {
        return $this->bloqueoDisponibilidadService->getAll();
    }

    public function destroy($id)
    {
        $bloqueo = BloqueoDisponibilidadTurno::find($id);

        if (!$bloqueo) {
            return response()->json([
                'message' => 'Bloqueo no encontrado'
            ], 404);
        }

        $bloqueo->delete();

        return response()->json([
            'message' => 'Bloqueo eliminado correctamente'
        ], 200);
    }
} 