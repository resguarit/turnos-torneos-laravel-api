<?php
// app/Http/Controllers/Api/GrupoController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Interface\GrupoServiceInterface;
use Illuminate\Http\Request;

class GrupoController extends Controller
{
    protected $grupoService;

    public function __construct(GrupoServiceInterface $grupoService)
    {
        $this->grupoService = $grupoService;
    }

    public function index()
    {
        return response()->json($this->grupoService->getAll(), 200);
    }

    public function show($id)
    {
        $grupo = $this->grupoService->getById($id);

        if (!$grupo) {
            return response()->json([
                'message' => 'Grupo no encontrado',
                'status' => 404
            ], 404);
        }

        return response()->json($grupo, 200);
    }

    public function store(Request $request)
    {
        return $this->grupoService->create($request);
    }

    public function update(Request $request, $id)
    {
        return $this->grupoService->update($request, $id);
    }

    public function destroy($id)
    {
        return $this->grupoService->delete($id);
    }

    public function getByZona($zonaId)
    {
        return response()->json($this->grupoService->getByZona($zonaId), 200);
    }

    public function eliminarEquipoDeGrupo($grupoId, $equipoId)
    {
        $result = $this->grupoService->eliminarEquipoDeGrupo($grupoId, $equipoId);

        return response()->json([
            'message' => $result['message'],
            'status' => $result['status'],
            'error' => $result['status'] === 500 ? $result['error'] : null,
        ], $result['status']);
    }

    public function eliminarGruposDeZona(Request $request, $zonaId)
    {
        return $this->grupoService->eliminarGruposDeZona($zonaId);
    }

    public function agregarEquipoAGrupo($grupoId, $equipoId)
    {
        $result = $this->grupoService->agregarEquipoAGrupo($grupoId, $equipoId);

        return response()->json([
            'message' => $result['message'],
            'status' => $result['status'],
            'error' => $result['status'] === 500 ? $result['error'] : null,
        ], $result['status']);
    }

    public function actualizarEquiposDeGrupo(Request $request, $grupoId)
    {
        $equipoIds = $request->input('equipos'); // Array de IDs de equipos

        $result = $this->grupoService->actualizarEquiposDeGrupo($grupoId, $equipoIds);

        return response()->json([
            'message' => $result['message'],
            'status' => $result['status'],
            'error' => $result['status'] === 500 ? $result['error'] : null,
        ], $result['status']);
    }
}