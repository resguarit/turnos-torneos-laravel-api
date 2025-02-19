<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\TurnoResource;
use App\Models\Horario;
use App\Models\Cancha;
use App\Models\TurnoCancelacion;
use Illuminate\Http\Request;
use App\Models\Turno;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\BloqueoTemporal;
use Carbon\Carbon;
use App\Models\TurnoModificacion;
use App\Services\TurnoServiceInterface;

class TurnoController extends Controller
{
    protected $turnoService;

    public function __construct(TurnoServiceInterface $turnoService)
    {
        $this->turnoService = $turnoService;
    }

    public function index(Request $request)
    {
        $user = Auth::user();

        abort_unless($user->tokenCan('turnos:show') || $user->rol === 'admin', 403, 'No tienes permisos para realizar esta acción');

        return $this->turnoService->getTurnos($request);
    }

    public function getAll()
    {
        $user = Auth::user();

        abort_unless($user->tokenCan('turnos:show_all') || $user->rol === 'admin',403, 'No tienes permisos para realizar esta acción');

        return $this->turnoService->getAllTurnos();   
    }

    public function storeTurnoUnico(Request $request)
    {
        $user = Auth::user();

        abort_unless($user->tokenCan('turnos:create') || $user->rol === 'admin', 403, 'No tienes permisos para realizar esta acción');

        return $this->turnoService->storeTurnoUnico($request);
    }

    public function storeTurnoFijo(Request $request)
    {
        $user = Auth::user();

        abort_unless($user->tokenCan('turnos:createTurnoFijo') || $user->rol === 'admin', 403, 'No tienes permisos para realizar esta acción');

        return $this->turnoService->storeTurnoFijo($request);
    }

    public function update(Request $request, $id)
    {
        $user = Auth::user();

        abort_unless( $user->tokenCan('turnos:update') || $user->rol === 'admin',403, 'No tienes permisos para realizar esta acción');

        return $this->turnoService->updateTurno($request, $id);
    }

    public function destroy($id)
    {
        $user = Auth::user();

        abort_unless( $user->tokenCan('turnos:destroy') || $user->rol === 'admin',403, 'No tienes permisos para realizar esta acción');

        return $this->turnoService->deleteTurno($id);
    }

    public function restore($id)
    {
        $user = Auth::user();

        abort_unless( $user->tokenCan('turnos:destroy') || $user->rol === 'admin',403, 'No tienes permisos para realizar esta acción');

        return $this->turnoService->restoreTurno($id);
    }

    public function show($id)
    {
        $user = Auth::user();

        abort_unless( $user->tokenCan('turnos:show') || $user->rol === 'admin',403, 'No tienes permisos para realizar esta acción');

        return $this->turnoService->showTurno($id);
    }

    public function grid(Request $request)
    {
        $user = Auth::user();

        abort_unless($user->tokenCan('turnos:show') || $user->rol === 'admin', 403, 'No tienes permisos para realizar esta acción');

        return $this->turnoService->gridTurnos($request);
    }

    public function getTurnosByUser($id = null)
    {
        $user = Auth::user();

        abort_unless($user->tokenCan('turnos:show') || $user->rol === 'admin', 403, 'No tienes permisos para realizar esta acción');

        if ($id) {
            abort_unless($user->rol === 'admin', 403, 'No tienes permisos para realizar esta acción');
            $userId = $id;
        } else {
            $userId = $user->id;
        }

        return $this->turnoService->getTurnosByUser($userId);
    }

    public function getProximos(){
        $user = Auth::user();

        abort_unless($user->tokenCan('turnos:show') || $user->rol === 'admin', 403, 'No tienes permisos para realizar esta acción');

        return $this->turnoService->getProximosTurnos();
    }

    public function cancel($id){
        $user = Auth::user();

        abort_unless($user->tokenCan('turnos:show') || $user->rol === 'admin', 403, 'No tienes permisos para realizar esta acción');

        return $this->turnoService->cancelTurno($id);
    }
}
