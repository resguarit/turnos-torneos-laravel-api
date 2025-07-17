<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Interface\ProfesorServiceInterface;
use Illuminate\Http\Request;

class ProfesorController extends Controller
{
    protected $profesorService;

    public function __construct(ProfesorServiceInterface $profesorService)
    {
        $this->profesorService = $profesorService;
    }

    public function index()
    {
        return response()->json($this->profesorService->getAll(), 200);
    }

    public function show($id)
    {
        $profesor = $this->profesorService->getById($id);

        if (!$profesor) {
            return response()->json([
                'message' => 'Profesor no encontrado',
                'status' => 404
            ], 404);
        }

        return response()->json($profesor, 200);
    }

    public function store(Request $request)
    {
        return $this->profesorService->create($request);
    }

    public function update(Request $request, $id)
    {
        return $this->profesorService->update($request, $id);
    }

    public function destroy($id)
    {
        return $this->profesorService->delete($id);
    }
}