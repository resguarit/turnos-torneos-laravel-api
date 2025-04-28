<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Interface\JugadorServiceInterface;
use Illuminate\Http\Request;

class JugadorController extends Controller
{
    protected $jugadorService;

    public function __construct(JugadorServiceInterface $jugadorService)
    {
        $this->jugadorService = $jugadorService;
    }

    public function index()
    {
        return response()->json($this->jugadorService->getAll(), 200);
    }

    public function show($id)
    {
        $jugador = $this->jugadorService->getById($id);

        if (!$jugador) {
            return response()->json([
                'message' => 'Jugador no encontrado',
                'status' => 404
            ], 404);
        }

        return response()->json($jugador, 200);
    }

    public function store(Request $request)
    {
        return $this->jugadorService->create($request);
    }

    public function update(Request $request, $id)
    {
        return $this->jugadorService->update($request, $id);
    }

    public function destroy($id)
    {
        return $this->jugadorService->delete($id);
    }

    public function getByEquipo($equipoId)
    {
        return response()->json($this->jugadorService->getByEquipo($equipoId), 200);
    }

    public function getByZona($zonaId)
    {
        return response()->json($this->jugadorService->getByZona($zonaId), 200);
    }

    public function createMultiple(Request $request)
    {
        return $this->jugadorService->createMultiple($request);
    }

    public function searchByDni(Request $request) 
    {
        return $this->jugadorService->searchByDni($request);
    }

    public function asociarJugadorAEquipo(Request $request)
    {
        $jugadorId = $request->input('jugador_id');
        $equipoId = $request->input('equipo_id');
        return $this->jugadorService->asociarJugadorAEquipo($jugadorId, $equipoId);
    }
}
