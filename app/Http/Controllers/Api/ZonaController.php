<?php
// app/Http/Controllers/Api/ZonaController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Interface\ZonaServiceInterface;
use Illuminate\Http\Request;

class ZonaController extends Controller
{
    protected $zonaService;

    public function __construct(ZonaServiceInterface $zonaService)
    {
        $this->zonaService = $zonaService;
    }

    public function index()
    {
        return response()->json($this->zonaService->getAll(), 200);
    }

    public function show($id)
    {
        $zona = $this->zonaService->getById($id);

        if (!$zona) {
            return response()->json([
                'message' => 'Zona no encontrada',
                'status' => 404
            ], 404);
        }

        return response()->json($zona, 200);
    }

    public function store(Request $request)
    {
        return $this->zonaService->create($request);
    }

    public function update(Request $request, $id)
    {
        return $this->zonaService->update($request, $id);
    }

    public function destroy($id)
    {
        return $this->zonaService->delete($id);
    }
}