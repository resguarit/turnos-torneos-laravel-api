<?php

namespace App\Http\Controllers\Checkout;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\MercadoPagoConfigService;
use MercadoPago\MercadoPagoConfig;
use MercadoPago\Client\Preference\PreferenceClient;
use MercadoPago\Exceptions\MPApiException;
use Illuminate\Support\Facades\Log;

class MercadoPagoTestController extends Controller
{
    /**
     * Devuelve el estado de configuración de MercadoPago
     */
    public function checkConfig()
    {
        $enabled = MercadoPagoConfigService::isEnabled();
        $token = MercadoPagoConfigService::getAccessToken();
        $webhookSecret = MercadoPagoConfigService::getWebhookSecret();
        
        return response()->json([
            'mercadopago_enabled' => $enabled,
            'token_masked' => $token ? substr($token, 0, 4) . '...' . substr($token, -4) : null,
            'webhook_secret_masked' => $webhookSecret ? substr($webhookSecret, 0, 4) . '...' . substr($webhookSecret, -4) : null
        ]);
    }
    
    /**
     * Prueba de conexión con MercadoPago
     */
    public function testConnection()
    {
        try {
            // Configurar MercadoPago
            MercadoPagoConfigService::configureMP();
            
            // Intentar crear una preferencia simple para probar la conexión
            $client = new PreferenceClient();
            
            $preference = $client->create([
                "items" => [
                    [
                        "title" => "Test Item",
                        "quantity" => 1,
                        "currency_id" => "ARS",
                        "unit_price" => 1.00
                    ]
                ]
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Conexión exitosa con MercadoPago',
                'preference_id' => $preference->id
            ]);
            
        } catch (MPApiException $e) {
            Log::error('Error en la API de MercadoPago: ' . $e->getMessage());
            Log::error('Detalles del error: ' . json_encode($e->getApiResponse()));
            
            return response()->json([
                'success' => false,
                'message' => 'Error al conectar con MercadoPago',
                'error' => $e->getMessage(),
                'api_response' => $e->getApiResponse()
            ], 500);
        } catch (\Exception $e) {
            Log::error('Error general al probar MercadoPago: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al conectar con MercadoPago',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
