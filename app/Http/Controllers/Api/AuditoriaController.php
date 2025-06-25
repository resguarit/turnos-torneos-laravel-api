<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Interface\AuditoriaServiceInterface;
use Illuminate\Http\Request;

class AuditoriaController extends Controller
{
    protected $auditoriaService;
    
    public function __construct(AuditoriaServiceInterface $auditoriaService)
    {
        $this->auditoriaService = $auditoriaService;
    }
    
    public function index(Request $request)
    {
        $filtros = [
            'tipo' => $request->get('tipo'),
            'usuario' => $request->get('usuario'),
            'fecha_inicio' => $request->get('fecha_inicio'),
            'fecha_fin' => $request->get('fecha_fin'),
        ];
        
        $perPage = $request->get('per_page', 15);
        
        $auditorias = $this->auditoriaService->obtenerAuditorias($filtros, $perPage);
        
        return response()->json([
            'auditorias' => $auditorias,
            'status' => 200
        ]);
    }

    public function tiposDeAccion()
    {
        return $this->auditoriaService->tiposDeAccion();
    }
}