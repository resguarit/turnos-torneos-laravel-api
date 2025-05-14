<?php

namespace App\Http\Controllers\Checkout;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use MercadoPago\Client\Preference\PreferenceClient;
use MercadoPago\MercadoPagoConfig;
use App\Models\Turno;
use Exception;
use Carbon\Carbon;
use MercadoPago\Client\Payment\PaymentClient;
use MercadoPago\Client\Payment\Search\MPSearchRequest;
use Illuminate\Support\Facades\Http;
use App\Enums\TurnoEstado;
use Illuminate\Support\Facades\Validator;   
class MercadoPagoController extends Controller
{
    public function __construct()
    {
        MercadoPagoConfig::setRuntimeEnviroment(MercadoPagoConfig::LOCAL);
    }

    public function createPreference(Request $request)
    {
        MercadoPagoConfig::setAccessToken(config('app.mercadopago_access_token'));
        try {
            $client = new PreferenceClient();

        $turno = Turno::where('id', $request->turno_id)->first();
        $unit_price = floatval($turno->monto_seña);

        $preference = $client->create([
            'items' => [
                [
                    'title' => 'Seña para turno #' . $turno->id,
                    'quantity' => 1,
                    'unit_price' => $unit_price,
                    'currency_id' => 'ARS',
                ]
            ],
            'external_reference' => $turno->id,
            'back_urls' => [
                'success' => config('app.url_front') . "/checkout/success/". $turno->id,
                'pending' => config('app.url_front') . "/checkout/pending/". $turno->id,
                'failure' => config('app.url_front') . "/checkout/failure/". $turno->id,
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
            'expires' => true,
            'expiration_date_from' => Carbon::now('America/Argentina/Buenos_Aires')->format('Y-m-d\TH:i:s.000P'),
            'expiration_date_to' => Carbon::now('America/Argentina/Buenos_Aires')->addMinutes(30)->format('Y-m-d\TH:i:s.000P'),
        ]);

        return response()->json([
                'status' => 'success',
                'preference' => $preference,
                'id' => $preference->id,
            ]);
        } catch (Exception $e) {
            // Log detallado del error
            \Log::error('MP API Error', [
                'status' => $e->getApiResponse()->getStatusCode(),
                'response' => $e->getApiResponse()->getContent(),
            ]);
            
            return response()->json([
                'error' => 'Error en MercadoPago',
                'details' => $e->getApiResponse()->getContent()
            ], 500);
        }
    }

    public function verifyPaymentStatus(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'external_reference' => 'required|exists:turnos,id',
            'payment_id' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Datos inválidos',
                'details' => $validator->errors()
            ], 422);
        }

        MercadoPagoConfig::setAccessToken(config('app.mercadopago_access_token'));
        $accessToken = config('app.mercadopago_access_token');

        $turnoId = $request->external_reference;
        $paymentId = $request->payment_id;
        
        $response = Http::withToken($accessToken)->get("https://api.mercadopago.com/v1/payments/{$paymentId}");

        if ($response->failed()) {
            return response()->json([
                'error' => 'Error al verificar el estado del pago',
            ], 500);
        }

        $data = $response->json();

        if ($data['external_reference'] != $turnoId) {
            return response()->json([
                'error' => 'El pago no pertenece al turno especificado',
            ], 400);
        }

        $turno = Turno::with(['cancha', 'horario', 'persona'])
            ->where('id', $turnoId)
            ->first();

        $estado = $data['status'];

        return response()->json([
            'turno' => $turno,
            'estado' => $estado,
        ]);

    }
}
