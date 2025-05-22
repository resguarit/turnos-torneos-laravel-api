<?php
// app/Http/Controllers/Api/EstadisticaController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Interface\EventoServiceInterface;
use Illuminate\Http\Request;

class EventoController extends Controller
{
    protected $eventoService;

    public function __construct(EventoServiceInterface $eventoService)
    {
        $this->eventoService = $eventoService;
    }

    public function index()
    {
        return response()->json($this->eventoService->getAll(), 200);
    }

    public function show($id)
    {
        $evento = $this->eventoService->getById($id);

        if (!$evento) {
            return response()->json([
                'message' => 'Evento no encontrado',
                'status' => 404
            ], 404);
        }

        return response()->json($evento, 200);
    }

    public function store(Request $request)
    {
        return $this->eventoService->create($request);
    }

    public function update(Request $request, $id)
    {
        return $this->eventoService->update($request, $id);
    }

    public function destroy($id)
    {
        return $this->eventoService->delete($id);
    }

    public function eventosComoTurnos()
    {
        return $this->eventoService->getEventosComoTurnos();
    }

    public function obtenerEstadopago($id)
    {
        return $this->eventoService->obtenerEstadopago($id);
    }
}