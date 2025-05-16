<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Turno;
use MercadoPago\MercadoPagoConfig;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use App\Services\Interface\PaymentServiceInterface;
use MercadoPago\Client\Payment\PaymentClient;

class MercadoPagoWebhook extends Controller
{
    protected $paymentService;
    
    public function __construct(PaymentServiceInterface $paymentService)
    {
        MercadoPagoConfig::setRuntimeEnviroment(MercadoPagoConfig::LOCAL);
        $this->paymentService = $paymentService;
    }

    public function handleWebhook(Request $request)
    {
        MercadoPagoConfig::setAccessToken(config('app.mercadopago_access_token'));
        $xSignature = $_SERVER['HTTP_X_SIGNATURE'];
        $xRequestId = $_SERVER['HTTP_X_REQUEST_ID'];

        $queryParams = $_GET;

        $dataID = isset($queryParams['data_id']) ? $queryParams['data_id'] : '';

        $parts = explode(',', $xSignature);

        $ts = null;
        $hash = null;

        foreach ($parts as $part) {
            $keyValue = explode('=', $part, 2);
            if (count($keyValue) == 2) {
                $key = trim($keyValue[0]);
                $value = trim($keyValue[1]);
                if ($key == 'ts') {
                    $ts = $value;
                } elseif ($key == 'v1') {
                    $hash = $value;
                }
            }
        }

        $secret = config('app.mercadopago_webhook_secret');

        $manifest = "id:$dataID;request-id:$xRequestId;ts:$ts;";

        $sha = hash_hmac('sha256', $manifest, $secret);

        if ($sha === $hash) {
            Log::info('Firma válida');

            switch ($_GET["type"]) {
                case "payment":
                    try {
                        $client = new PaymentClient();
                        $payment = $client->get($dataID);
                    } catch (MPApiException $e) {
                        Log::info(var_dump($e->getApiResponse()->getContent()));
                    } catch (\Exception $e) {
                        Log::info($e->getMessage());
                    }

                    // Toda la logica de pago se maneja en el paymentService
                    $this->paymentService->handleNewPayment($payment);

                    break;
                case "invoice":
                    Log::info("Invoice");
                    break;
            }
            
        } else {
            Log::error('Firma inválida');
            return response()->json(['error' => 'Firma inválida'], 400);
        }
    }
}

