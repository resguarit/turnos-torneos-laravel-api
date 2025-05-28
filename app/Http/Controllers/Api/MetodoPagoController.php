<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\MetodoPago;

class MetodoPagoController extends Controller
{
    public function index()
    {
        $metodosPago = MetodoPago::all();
        return response()->json($metodosPago, 200);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:255',
            'descripcion' => 'required|string|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        $metodoPago = MetodoPago::create(
            [
                'nombre' => $request->nombre,
                'descripcion' => $request->descripcion,
                'activo' => true
            ]
        );

        return response()->json($metodoPago, 201);
    }

    public function show($id)
    {
        $metodoPago = MetodoPago::find($id);
        if (!$metodoPago) {
            return response()->json(['message' => 'Método de pago no encontrado'], 404);
        }
        return response()->json($metodoPago, 200);
    }

    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:255',
            'descripcion' => 'required|string|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        $metodoPago = MetodoPago::find($id);
        $metodoPago->update($request->all());
        return response()->json($metodoPago, 200);
    }

    public function destroy($id)
    {
        $metodoPago = MetodoPago::find($id);
        $metodoPago->delete();
        return response()->json(['message' => 'Método de pago eliminado'], 200);
    }
}
