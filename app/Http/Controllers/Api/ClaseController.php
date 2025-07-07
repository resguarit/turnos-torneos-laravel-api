<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Interface\ClaseServiceInterface;
use Illuminate\Http\Request;

class ClaseController extends Controller
{
    protected $claseService;

    public function __construct(ClaseServiceInterface $claseService)
    {
        $this->claseService = $claseService;
    }

    public function index()
    {
        return response()->json($this->claseService->getAll(), 200);
    }

    public function show($id)
    {
        $clase = $this->claseService->getById($id);

        if (!$clase) {
            return response()->json([
                'message' => 'Clase no encontrada',
                'status' => 404
            ], 404);
        }

        return response()->json($clase, 200);
    }

    public function store(Request $request)
    {
        return $this->claseService->create($request);
    }

    public function update(Request $request, $id)
    {
        return $this->claseService->update($request, $id);
    }

    public function destroy($id)
    {
        return $this->claseService->delete($id);
    }
    
    public function crearClasesFijas(Request $request)
    {
        return $this->claseService->crearClasesFijas($request);
    }
}