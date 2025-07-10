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
use App\Models\Persona;
use App\Models\Transaccion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use MercadoPago\Client\Payment\PaymentRefundClient;
use MercadoPago\Exceptions\MPApiException;
use App\Services\MercadoPagoConfigService;

class MercadoPagoController extends Controller
{
    public function __construct()
    {
        MercadoPagoConfig::setRuntimeEnviroment(MercadoPagoConfig::LOCAL);
    }

    public function createPreference(Request $request)
    {
        // Configurar MercadoPago con las credenciales de la base de datos
        MercadoPagoConfigService::configureMP();
        
        $subdominio = $request->header('x-complejo');
        
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
                'success' => tenant_url($subdominio, "/checkout/success/". $turno->id),
                'pending' => tenant_url($subdominio, "/checkout/pending/". $turno->id),
                'failure' => tenant_url($subdominio, "/checkout/failure/". $turno->id),
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
            'binary_mode' => true,
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

        // Configurar MercadoPago con las credenciales de la base de datos
        MercadoPagoConfigService::configureMP();

        $turnoId = $request->external_reference;
        $paymentId = $request->payment_id;

        $client = new PaymentClient();
        try {
            $payment = $client->get($paymentId);
        } catch (MPApiException $e) {
            return response()->json([
                'error' => 'Error al verificar el estado del pago',
                'details' => $e->getApiResponse()->getContent(),
                'status' => $e->getApiResponse()->getStatusCode(),
            ], 500);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al verificar el estado del pago',
                'details' => $e->getMessage(),
            ], 500);
        }

        if ($payment->external_reference != $turnoId) {
            return response()->json([
                'error' => 'El pago no pertenece al turno especificado',
            ], 400);
        }

        $turno = Turno::with(['cancha', 'horario', 'persona'])
            ->where('id', $turnoId)
            ->first();

        $estado = $payment->status;

        return response()->json([
            'turno' => $turno,
            'estado' => $estado,
        ]);

    }

    public function verifyPaymentStatusByPreference(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'preference_id' => 'required|string',
            'external_reference' => 'required|exists:turnos,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Datos inválidos',
                'details' => $validator->errors()
            ], 422);
        }

        // Configurar MercadoPago con las credenciales de la base de datos
        MercadoPagoConfigService::configureMP();

        $client = new PreferenceClient();
        $preference = $client->get($request->preference_id);

        if ($preference->external_reference != $request->external_reference) {
            return response()->json([
                'error' => 'El pago no pertenece al turno especificado',
            ], 400);
        }

        DB::beginTransaction();

        try {

            $turno = Turno::where('id', $request->external_reference)->first();

            if ($turno->estado == TurnoEstado::PENDIENTE) {
                $turno->estado = TurnoEstado::CANCELADO;
                $turno->save();

                $persona = Persona::where('id', $turno->persona_id)->first();

                Transaccion::create([
                    'cuenta_corriente_id' => $persona->cuentaCorriente->id,
                    'monto' => $turno->monto_total,
                    'turno_id' => $turno->id,
                    'tipo' => 'saldo',
                    'descripcion' => 'Cancelacion de turno #' . $turno->id . '(Por falta de pago)',
                ]);

                $persona->cuentaCorriente->saldo += $turno->monto_total;
                $persona->cuentaCorriente->save();

                DB::commit();

                return response()->json([
                    'status' => 'success',
                    'message' => 'Turno cancelado',
                ], 200);
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Turno ya cancelado',
            ], 200);

        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Error al verificar el estado del pago',
                'details' => $e->getMessage(),
            ], 500);
        }
    }
    
}
