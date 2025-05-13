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
        MercadoPagoConfig::setRuntimeEnviroment(MercadoPagoConfig::LOCAL);
        MercadoPagoConfig::setAccessToken(config('app.mercadopago_access_token'));
    }

    public function createPreference(Request $request)
    {
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
    }
}
