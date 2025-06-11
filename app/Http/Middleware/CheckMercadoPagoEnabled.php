<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Services\MercadoPagoConfigService;

class CheckMercadoPagoEnabled
{
    /**
     * Verifica si MercadoPago está habilitado antes de procesar solicitudes de pago
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Verificar si MercadoPago está habilitado
        if (!MercadoPagoConfigService::isEnabled()) {
            return response()->json([
                'error' => 'El servicio de MercadoPago está deshabilitado temporalmente',
                'message' => 'Por favor, contacte al administrador o intente con otro método de pago'
            ], 503); // 503 Service Unavailable
        }
        
        return $next($request);
    }
} 