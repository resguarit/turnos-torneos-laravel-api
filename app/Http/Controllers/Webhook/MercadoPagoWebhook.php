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
use App\Services\MercadoPagoConfigService;
use MercadoPago\Exceptions\MPApiException;

class MercadoPagoWebhook extends Controller
{
    protected $paymentService;
    
    public function __construct(PaymentServiceInterface $paymentService)
    {
        $this->paymentService = $paymentService;
    }

    public function handleWebhook(Request $request)
    {
        // Configurar MercadoPago con las credenciales de la base de datos
        MercadoPagoConfigService::configureMP();
        
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

        // Usar el webhook secret de la base de datos
        $secret = MercadoPagoConfigService::getWebhookSecret();

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
                        Log::error('Error al obtener el pago', [
                            'message' => $e->getMessage(),
                            'response' => isset($e->getApiResponse) ? $e->getApiResponse()->getContent() : null
                        ]);
                        return response()->json(['error' => 'Error al obtener el pago'], 500);
                    } catch (\Exception $e) {
                        Log::error('Excepción al procesar el webhook', [
                            'message' => $e->getMessage()
                        ]);
                        return response()->json(['error' => 'Error al procesar el webhook'], 500);
                    }

                    // Toda la logica de pago se maneja en el paymentService
                    $this->paymentService->handleNewPayment($payment);
                    
                    return response()->json(['status' => 'success'], 200);
                    break;
                    
                case "invoice":
                    Log::info("Invoice notification received");
                    return response()->json(['status' => 'success'], 200);
                    break;
                    
                default:
                    Log::info("Unhandled notification type: " . $_GET["type"]);
                    return response()->json(['status' => 'success'], 200);
            }
            
        } else {
            Log::error('Firma inválida');
            return response()->json(['error' => 'Firma inválida'], 400);
        }
    }
}

