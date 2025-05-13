<?php

namespace App\Http\Controllers\Checkout;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use MercadoPago\Client\Preference\PreferenceClient;
use MercadoPago\MercadoPagoConfig;
use App\Models\Turno;

class MercadoPagoController extends Controller
{
    public function __construct()
    {   
    }

    public function createPreference(Request $request)
    {
        MercadoPagoConfig::setAccessToken(config('app.mercadopago_access_token'));
        try {
            $client = new PreferenceClient();

        $turno = Turno::where('id', $request->turno_id)->first();

        $preference = $client->create([
            'items' => [
                [
                    'title' => 'Seña para turno #' . $turno->id,
                    'quantity' => 1,
                    'unit_price' => $turno->monto_seña,
                    'currency_id' => 'ARS',
                ]
            ],
            'external_reference' => $turno->id,
            'back_urls' => [
                'success' => config('app.url_front') . "/turno/success/". $turno->id,
                'pending' => config('app.url_front') . "/turno/pending/". $turno->id,
                'failure' => config('app.url_front') . "/turno/failure/". $turno->id,
            ],
            'auto_return' => 'approved',
            'payment_methods' => [
                'excluded_payment_types' => [
                    [
                        'id' => "ticket",
                    ]
                ],
                'installments' => 1,
            ],
            'redirect_mode' => 'modal',
        ]);

        return response()->json([
                'status' => 'success',
                'preference' => $preference,
                'id' => $preference->id,
            ]);
        } catch (MPApiException $e) {
            // Log detallado del error
            \Log::error('MP API Error', [
                'status' => $e->getApiResponse()->getStatusCode(),
                'response' => $e->getApiResponse()->getContent(),
                'headers' => $e->getApiResponse()->getHeaders()
            ]);
            
            return response()->json([
                'error' => 'Error en MercadoPago',
                'details' => $e->getApiResponse()->getContent()
            ], 500);
        }
    }
}
