<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\Interface\BloqueoTemporalServiceInterface;

class BloqueoTemporalController extends Controller
{
    protected $bloqueoTemporalService;

    public function __construct(BloqueoTemporalServiceInterface $bloqueoTemporalService)
    {
        $this->bloqueoTemporalService = $bloqueoTemporalService;
    }

    public function bloquearHorario(Request $request)
    {
        $user = Auth::user();
        abort_unless($user->tokenCan('turnos:bloqueo') || $user->rol === 'admin', 403, 'No tienes permisos para realizar esta acción');
        
        return $this->bloqueoTemporalService->bloquearHorario($request);
    }

    public function cancelarBloqueo($id)
    {
        $user = Auth::user();
        abort_unless($user->tokenCan('turnos:cancelarBloqueo') || $user->rol === 'admin', 403, 'No tienes permisos para realizar esta acción');
        
        return $this->bloqueoTemporalService->cancelarBloqueo($id);
    }
}