<?php

namespace App\Http\Controllers;

use App\Models\MetodoPago;
use Illuminate\Http\Request;

class MetodoPagoController extends Controller
{
    public function index()
    {
        $metodosPago = MetodoPago::all();
        return response()->json($metodosPago, 200);
    }

    public function store(Request $request)
    {
        $request->validate([
            'nombre' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'activo' => 'boolean',
        ]);

        $metodoPago = MetodoPago::create($request->all());
        return response()->json($metodoPago, 201);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'nombre' => 'sometimes|string|max:255',
            'descripcion' => 'nullable|string',
            'activo' => 'sometimes|boolean',
        ]);

        $metodoPago = MetodoPago::findOrFail($id);
        $metodoPago->update($request->all());
        return response()->json($metodoPago, 200);
    }

    public function destroy($id)
    {
        $metodoPago = MetodoPago::findOrFail($id);
        $metodoPago->delete();
        return response()->json(null, 204);
    }
}
