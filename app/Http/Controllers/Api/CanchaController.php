<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\Interface\CanchaServiceInterface;
use App\Http\Controllers\Controller;

class CanchaController extends Controller
{
    protected $canchaService;

    public function __construct(CanchaServiceInterface $canchaService)
    {
        $this->canchaService = $canchaService;
    }

    public function index()
    {
        $user = Auth::user();
        abort_unless($user->tokenCan('canchas:show') || $user->rol === 'admin', 403, 'No tienes permisos para realizar esta acción');
        
        return $this->canchaService->getCanchas();
    }

    public function show($id)
    {
        $user = Auth::user();
        abort_unless($user->tokenCan('cancha:showOne') || $user->rol === 'admin', 403, 'No tienes permisos para realizar esta acción');
        
        return $this->canchaService->showCancha($id);
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        abort_unless($user->tokenCan('canchas:create') || $user->rol === 'admin', 403, 'No tienes permisos para realizar esta acción');
        
        return $this->canchaService->storeCancha($request);
    }

    public function update(Request $request, $id)
    {
        $user = Auth::user();
        abort_unless($user->tokenCan('canchas:update') || $user->rol === 'admin', 403, 'No tienes permisos para realizar esta acción');
        
        return $this->canchaService->updateCancha($request, $id);
    }

    public function destroy($id)
    {
        $user = Auth::user();
        abort_unless($user->tokenCan('canchas:destroy') || $user->rol === 'admin', 403, 'No tienes permisos para realizar esta acción');
        
        return $this->canchaService->deleteCancha($id);
    }
}
