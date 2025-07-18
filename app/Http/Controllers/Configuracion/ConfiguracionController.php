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
        $logoUrl = $configuracion && $configuracion->logo_complejo
            ? asset('storage/' . $configuracion->logo_complejo)
            : null;

        $configuracionArray = $configuracion ? $configuracion->toArray() : [];
        $configuracionArray['logo_complejo_url'] = $logoUrl;

        return response()->json($configuracionArray);
    }

    public function ObtenerConfiguracion()
    {
        $configuracion = Configuracion::first();
        $logoUrl = $configuracion && $configuracion->logo_complejo
            ? asset('storage/' . $configuracion->logo_complejo)
            : null;
        
        // Enmascarar parcialmente las credenciales de Mercado Pago
        $accessToken = '';
        $webhookSecret = '';
        
        if ($configuracion->mercado_pago_access_token) {
            $accessToken = substr($configuracion->mercado_pago_access_token, 0, 4) . '...' . 
                          substr($configuracion->mercado_pago_access_token, -4);
        }
        
        if ($configuracion->mercado_pago_webhook_secret) {
            $webhookSecret = substr($configuracion->mercado_pago_webhook_secret, 0, 4) . '...' . 
                            substr($configuracion->mercado_pago_webhook_secret, -4);
        }
        
        return response()->json([
            'colores' => $configuracion->colores,
            'habilitar_turnos' => $configuracion->habilitar_turnos,
            'habilitar_mercado_pago' => $configuracion->habilitar_mercado_pago,
            'nombre_complejo' => $configuracion->nombre_complejo,
            'direccion_complejo' => $configuracion->direccion_complejo,
            'telefono_complejo' => $configuracion->telefono_complejo,
            'logo_complejo_url' => $logoUrl,
            'mercado_pago_public_key' => $configuracion->mercado_pago_public_key,
            'mercado_pago_access_token' => $accessToken,
            'mercado_pago_webhook_secret' => $webhookSecret,
        ]);
    }
    
    public function actualizarConfiguracion(Request $request)
    {
        $validator = \Validator::make($request->all(), [
            'colores.primary' => 'required|string',
            'colores.secondary' => 'required|string',
            'habilitar_turnos' => 'required|boolean',
            'habilitar_mercado_pago' => 'required|boolean',
            'nombre_complejo' => 'required|string',
            'direccion_complejo' => 'required|string',
            'telefono_complejo' => 'required|string',
            'mercado_pago_public_key' => 'nullable|string',
            'mercado_pago_access_token' => 'nullable|string',
            'mercado_pago_webhook_secret' => 'nullable|string',
            'logo_complejo' => 'nullable|image|max:2048', // Validar imagen
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        $configuracion = Configuracion::first();

        $dataToUpdate = [
            'colores' => $request->colores,
            'habilitar_turnos' => $request->habilitar_turnos,
            'habilitar_mercado_pago' => $request->habilitar_mercado_pago,
            'nombre_complejo' => $request->nombre_complejo,
            'direccion_complejo' => $request->direccion_complejo,
            'telefono_complejo' => $request->telefono_complejo,
            'mercado_pago_public_key' => $request->mercado_pago_public_key,
        ];

        // Manejar el logo del complejo
        if ($request->hasFile('logo_complejo')) {
            $path = $request->file('logo_complejo')->store('logos', 'public');
            $dataToUpdate['logo_complejo'] = $path;
        }

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

        $logoUrl = $configuracion->logo_complejo
            ? asset('storage/' . $configuracion->logo_complejo)
            : null;

        return response()->json([
            'message' => 'Configuración actualizada correctamente',
            'configuracion' => [
                'colores' => $configuracion->colores,
                'habilitar_turnos' => $configuracion->habilitar_turnos,
                'habilitar_mercado_pago' => $configuracion->habilitar_mercado_pago,
                'nombre_complejo' => $configuracion->nombre_complejo,
                'direccion_complejo' => $configuracion->direccion_complejo,
                'telefono_complejo' => $configuracion->telefono_complejo,
                'logo_complejo_url' => $logoUrl,
            ]
        ]);
    }
}
