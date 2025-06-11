<?php

namespace App\Http\Controllers;

use App\Models\TipoGasto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TipoGastoController extends Controller
{
    /**
     * Obtener todos los tipos de gasto.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        try {
            $tiposGasto = TipoGasto::orderBy('nombre')->get();
            
            return response()->json([
                'success' => true,
                'tipos_gasto' => $tiposGasto
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener los tipos de gasto: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Almacenar un nuevo tipo de gasto.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'nombre' => 'required|string|max:100|unique:tipos_gasto'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error de validación',
                    'errors' => $validator->errors()
                ], 422);
            }

            $tipoGasto = TipoGasto::create([
                'nombre' => $request->nombre
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Tipo de gasto creado correctamente',
                'tipo_gasto' => $tipoGasto
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al crear el tipo de gasto: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar un tipo de gasto existente.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        try {
            $tipoGasto = TipoGasto::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'nombre' => 'required|string|max:100|unique:tipos_gasto,nombre,' . $id
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error de validación',
                    'errors' => $validator->errors()
                ], 422);
            }

            $tipoGasto->update([
                'nombre' => $request->nombre
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Tipo de gasto actualizado correctamente',
                'tipo_gasto' => $tipoGasto
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el tipo de gasto: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar un tipo de gasto.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        try {
            $tipoGasto = TipoGasto::findOrFail($id);
            
            // Verificar si tiene transacciones asociadas
            if ($tipoGasto->transacciones()->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede eliminar el tipo de gasto porque tiene transacciones asociadas'
                ], 400);
            }
            
            $tipoGasto->delete();

            return response()->json([
                'success' => true,
                'message' => 'Tipo de gasto eliminado correctamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar el tipo de gasto: ' . $e->getMessage()
            ], 500);
        }
    }
} 