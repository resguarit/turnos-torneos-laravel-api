<?php

namespace App\Http\Controllers\Configuracion;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Configuracion;

class ConfiguracionController extends Controller
{
    public function index()
    {
        $configuracion = Configuracion::first();
        return response()->json($configuracion);
    }

    public function ObtenerConfiguracion()
    {
        $configuracion = Configuracion::first();
        return response()->json([
            'colores' => $configuracion->colores,
            'habilitar_turnos' => $configuracion->habilitar_turnos,
            'habilitar_mercado_pago' => $configuracion->habilitar_mercado_pago,
            'direccion_complejo' => $configuracion->direccion_complejo,
            'telefono_complejo' => $configuracion->telefono_complejo,
        ]);
    }
    
    public function actualizarConfiguracion(Request $request)
    {
        $validator = \Validator::make($request->all(), [
            'colores.primary' => 'required|string',
            'colores.secondary' => 'required|string',
            'habilitar_turnos' => 'required|boolean',
            'habilitar_mercado_pago' => 'required|boolean',
            'direccion_complejo' => 'required|string',
            'telefono_complejo' => 'required|string',
            'mercado_pago_access_token' => 'nullable|string',
            'mercado_pago_webhook_secret' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        $configuracion = Configuracion::first();
        
        // Preparar los datos para actualizar
        $dataToUpdate = [
            'colores' => $request->colores,
            'habilitar_turnos' => $request->habilitar_turnos,
            'habilitar_mercado_pago' => $request->habilitar_mercado_pago,
            'direccion_complejo' => $request->direccion_complejo,
            'telefono_complejo' => $request->telefono_complejo,
        ];
        
        // Solo actualizar las credenciales si se proporcionan en la solicitud
        // y si Mercado Pago está habilitado
        if ($request->habilitar_mercado_pago) {
            if ($request->has('mercado_pago_access_token')) {
                $dataToUpdate['mercado_pago_access_token'] = $request->mercado_pago_access_token;
            }
            
            if ($request->has('mercado_pago_webhook_secret')) {
                $dataToUpdate['mercado_pago_webhook_secret'] = $request->mercado_pago_webhook_secret;
            }
        } else {
            // Si MP está deshabilitado, podemos establecer los valores a null
            // o mantenerlos (dependiendo de la lógica de negocio)
            // Aquí elijo mantenerlos para que si se vuelve a habilitar, se conserven
        }
        
        $configuracion->update($dataToUpdate);

        return response()->json([
            'message' => 'Configuración actualizada correctamente',
            'configuracion' => [
                'colores' => $configuracion->colores,
                'habilitar_turnos' => $configuracion->habilitar_turnos,
                'habilitar_mercado_pago' => $configuracion->habilitar_mercado_pago,
                'direccion_complejo' => $configuracion->direccion_complejo,
                'telefono_complejo' => $configuracion->telefono_complejo,
            ]
        ]);
    }
}
