<?php
// app/Http/Controllers/Api/EquipoController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Interface\EquipoServiceInterface;
use Illuminate\Http\Request;

class EquipoController extends Controller
{
    protected $equipoService;

    public function __construct(EquipoServiceInterface $equipoService)
    {
        $this->equipoService = $equipoService;
    }

    public function index()
    {
        return response()->json($this->equipoService->getAll(), 200);
    }

    public function show($id)
    {
        $equipo = $this->equipoService->getById($id);

        if (!$equipo) {
            return response()->json([
                'message' => 'Equipo no encontrado',
                'status' => 404
            ], 404);
        }

        return response()->json($equipo, 200);
    }

    public function store(Request $request)
    {
        return $this->equipoService->create($request);
    }

    public function update(Request $request, $id)
    {
        return $this->equipoService->update($request, $id);
    }

    public function destroy($id)
    {
        return $this->equipoService->delete($id);
    }
}