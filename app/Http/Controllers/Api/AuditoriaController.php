<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Interface\AuditoriaServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuditoriaController extends Controller
{
    protected $auditoriaService;
    
    public function __construct(AuditoriaServiceInterface $auditoriaService)
    {
        $this->auditoriaService = $auditoriaService;
    }
    
    public function index(Request $request)
    {
        $user = Auth::user();
        abort_unless($user->rol === 'admin', 403, 'No tienes permisos para realizar esta acción');

        $filtros = $request->only([
            'entidad',
            'accion',
            'fecha_desde',
            'fecha_hasta',
            'usuario_id'
        ]);
        
        $perPage = $request->query('per_page', 15);
        $auditorias = $this->auditoriaService->obtenerAuditorias($filtros, $perPage);
        
        return response()->json([
            'auditorias' => $auditorias,
            'status' => 200
        ]);
    }
}