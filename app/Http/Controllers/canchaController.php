<?php

namespace App\Http\Controllers;

use App\Models\Cancha;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class canchaController extends Controller
{
    public function index(){
        $canchas = Cancha::all();

        $data = [
            'canchas' => $canchas,
            'status' => 200
        ];

        return response()->json($data, 200);
    }

    public function store(Request $request){

        $validator = Validator::make($request->all(), [
            'nro' => 'required|unique:canchas',
            'tipoCancha' => 'required',
            'precioPorHora' => 'required'
        ]);

        if ($validator->fails()) {
            $data = [
                'message' => 'Error en la validacion',
                'errors' => $validator->errors(),
                'status' => 400
            ];
            return response()->json($data, 400);
        }

        $cancha = Cancha::create([
            'nro' => $request->nro,
            'tipoCancha' => $request->tipoCancha,
            'precioPorHora' => $request->precioPorHora
        ]);

        if (!$cancha) {
            $data = [
                'message' => 'Error al crear la cancha',
                'status' => 500
            ];
            return response()->json($data, 500);
        }

        $data = [
            'message' => 'Cancha creada correctamente',
            'cancha' => $cancha,
            'status' => 201
        ];

        return response()->json($data, 201);
    }
}
