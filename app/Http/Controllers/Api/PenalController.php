<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Interface\PenalServiceInterface;
use Illuminate\Http\Request;

class PenalController extends Controller
{
    protected $penalService;

    public function __construct(PenalServiceInterface $penalService)
    {
        $this->penalService = $penalService;
    }

    public function index()
    {
        return response()->json($this->penalService->getAll(), 200);
    }

    public function show($id)
    {
        $penal = $this->penalService->getById($id);

        if (!$penal) {
            return response()->json([
                'message' => 'Penal no encontrado',
                'status' => 404
            ], 404);
        }

        return response()->json($penal, 200);
    }

    public function store(Request $request)
    {
        return $this->penalService->create($request);
    }

    public function update(Request $request, $id)
    {
        return $this->penalService->update($request, $id);
    }

    public function destroy($id)
    {
        return $this->penalService->delete($id);
    }

    public function getByPartido($partidoId)
    {
        return response()->json($this->penalService->getByPartido($partidoId), 200);
    }
}