<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Implementation\SancionService;
use Illuminate\Http\Request;

class SancionController extends Controller
{
    protected $sancionService;

    public function __construct(SancionService $sancionService)
    {
        $this->sancionService = $sancionService;
    }

    public function store(Request $request)
    {
        $result = $this->sancionService->createSancion($request->all());
        return response()->json($result, $result['status']);
    }

    public function show($id)
    {
        $result = $this->sancionService->getSancionById($id);
        return response()->json($result, $result['status']);
    }

    public function destroy($id)
    {
        $result = $this->sancionService->deleteSancion($id);
        return response()->json($result, $result['status']);
    }

    public function getSancionesPorZona($zonaId)
    {
        $result = $this->sancionService->getSancionesPorZona($zonaId);
        return response()->json($result, $result['status']);
    }

    public function updateSancion(Request $request, $id)
    {
        $result = $this->sancionService->updateSancion($request->all(), $id);
        return response()->json($result, $result['status']);
    }

    public function getExpulsionesPermanentes()
    {
        $result = $this->sancionService->getExpulsionesPermanentes();
        return response()->json($result, $result['status']);
    }
}