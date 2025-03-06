<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Interface\DeporteServiceInterface;
use Illuminate\Http\Request;

class DeporteController extends Controller
{
    protected $deporteService;

    public function __construct(DeporteServiceInterface $deporteService)
    {
        $this->deporteService = $deporteService;
    }

    public function index()
    {
        return response()->json($this->deporteService->getAll(), 200);
    }

    public function show($id)
    {
        $deporte = $this->deporteService->getById($id);

        if (!$deporte) {
            return response()->json([
                'message' => 'Deporte no encontrado',
                'status' => 404
            ], 404);
        }

        return response()->json($deporte, 200);
    }

    public function store(Request $request)
    {
        return $this->deporteService->create($request);
    }

    public function update(Request $request, $id)
    {
        return $this->deporteService->update($request, $id);
    }

    public function destroy($id)
    {
        return $this->deporteService->delete($id);
    }
}
