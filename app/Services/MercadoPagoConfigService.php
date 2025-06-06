<?php

namespace App\Services;

use App\Models\Configuracion;
use MercadoPago\MercadoPagoConfig;
use Illuminate\Support\Facades\Log;

class MercadoPagoConfigService
{
    /**
     * Configura el entorno y token de acceso de MercadoPago
     * 
     * @return void
     */
    public static function configureMP()
    {
        MercadoPagoConfig::setRuntimeEnviroment(MercadoPagoConfig::LOCAL);
        
        $config = Configuracion::first();
        
        if ($config && $config->habilitar_mercado_pago) {
            // Usa las credenciales de la base de datos en lugar de las del archivo .env
            Log::info('Configurando MercadoPago con las credenciales de la base de datos');
            Log::info($config->mercado_pago_access_token);
            MercadoPagoConfig::setAccessToken($config->mercado_pago_access_token);
        } else {
            // Fallback al valor de .env si no hay configuración en la base de datos
            Log::info('Configurando MercadoPago con las credenciales de .env');
            Log::info(config('app.mercadopago_access_token'));
            MercadoPagoConfig::setAccessToken(config('app.mercadopago_access_token'));
        }
    }
    
    /**
     * Obtiene el token de acceso de MercadoPago
     * 
     * @return string
     */
    public static function getAccessToken()
    {
        $config = Configuracion::first();
        
        if ($config && $config->habilitar_mercado_pago) {
            Log::info('Obtiene el token de acceso de MercadoPago de la base de datos');
            Log::info($config->mercado_pago_access_token);
            return $config->mercado_pago_access_token;
        }
        
        return config('app.mercadopago_access_token');
    }
    
    /**
     * Obtiene el webhook secret de MercadoPago
     * 
     * @return string
     */
    public static function getWebhookSecret()
    {
        $config = Configuracion::first();
        
        if ($config && $config->habilitar_mercado_pago) {
            Log::info('Obtiene el webhook secret de MercadoPago de la base de datos');
            Log::info($config->mercado_pago_webhook_secret);
            return $config->mercado_pago_webhook_secret;
        }
        
        return config('app.mercadopago_webhook_secret');
    }
    
    /**
     * Verifica si MercadoPago está habilitado
     * 
     * @return bool
     */
    public static function isEnabled()
    {
        $config = Configuracion::first();
        
        Log::info('Verifica si MercadoPago está habilitado');
        Log::info($config->habilitar_mercado_pago);
        return $config && $config->habilitar_mercado_pago;
    }
} 