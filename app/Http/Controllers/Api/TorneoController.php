<?php
// app/Http/Controllers/Api/TorneoController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Interface\TorneoServiceInterface;
use Illuminate\Http\Request;

class TorneoController extends Controller
{
    protected $torneoService;

    public function __construct(TorneoServiceInterface $torneoService)
    {
        $this->torneoService = $torneoService;
    }

    public function index()
    {
        return response()->json($this->torneoService->getAll(), 200);
    }

    public function show($id)
    {
        $torneo = $this->torneoService->getById($id);

        if (!$torneo) {
            return response()->json([
                'message' => 'Torneo no encontrado',
                'status' => 404
            ], 404);
        }

        return response()->json($torneo, 200);
    }

    public function store(Request $request)
    {
        return $this->torneoService->create($request);
    }

    public function update(Request $request, $id)
    {
        return $this->torneoService->update($request, $id);
    }

    public function destroy($id)
    {
        return $this->torneoService->delete($id);
    }
}