<?php

namespace App\Services\Implementation;

use App\Services\Interface\CajaServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use App\Models\Caja;
use App\Models\Transaccion;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CajaService implements CajaServiceInterface
{
    public function getCaja()
    {
        $caja = Caja::where('activa', true)
            ->with(['empleado', 'transacciones' => function($query) {
                $query->orderBy('created_at', 'desc');
            }, 'transacciones.metodoPago'])
            ->first();

        if (!$caja) {
            return response()->json([
                'message' => 'No hay caja abierta',
                'status' => 404
            ], 404);
        }

        // Calcular balance total y efectivo en caja
        $balanceTotal = 0;
        $efectivoEnCaja = 0;
        $resumenPagos = [
            'efectivo' => 0,
            'transferencia' => 0,
            'tarjeta' => 0,
            'mercadopago' => 0
        ];

        // Procesar todas las transacciones una sola vez
        foreach ($caja->transacciones as $transaccion) {
            $balanceTotal += $transaccion->monto;
            
            // Asegurarse que metodoPago no sea null antes de acceder a su nombre
            if ($transaccion->metodoPago) {
                $metodoPagoNombre = strtolower($transaccion->metodoPago->nombre);

                if ($metodoPagoNombre === 'efectivo') {
                    $efectivoEnCaja += $transaccion->monto;
                }
                // Sumar al resumen del método de pago correspondiente
                if (array_key_exists($metodoPagoNombre, $resumenPagos)) {
                    $resumenPagos[$metodoPagoNombre] += $transaccion->monto;
                } else {
                    // Opcional: Loguear si se encuentra un método de pago no esperado en el resumen
                    Log::warning("CajaService: Método de pago '{$metodoPagoNombre}' no está en el resumen inicial de pagos para caja ID: {$caja->id}");
                }
            } else {
                Log::warning("CajaService: Transacción ID {$transaccion->id} no tiene metodoPago asociado para caja ID: {$caja->id}");
            }
        }

        // Formatear transacciones para el frontend
        $transacciones = $caja->transacciones->map(function ($transaccion) {
            return [
                'id' => $transaccion->id,
                'tipo' => (($transaccion->monto > 0) || $transaccion->tipo === 'inscripcion' || $transaccion->tipo === 'fecha') ? 'deposito' : 'retiro',
                'monto' => abs($transaccion->monto),
                'descripcion' => $transaccion->descripcion,
                'metodo_pago' => strtolower($transaccion->metodoPago->nombre),
                'fecha' => $transaccion->created_at
            ];
        });

        return response()->json([
            'status' => 200,
            'caja' => $caja,
            'operador' => $caja->empleado->name,
            'balance_total' => $balanceTotal,
            'efectivo_en_caja' => $efectivoEnCaja,
            'transacciones' => $transacciones,
            'resumen_pagos' => $resumenPagos
        ], 200);
    }

    public function abrirCaja(Request $request)
    {
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'saldo_inicial' => 'required|numeric|min:0'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error en la validación',
                'errors' => $validator->errors(),
                'status' => 400
            ], 400);
        }

        $cajaExistente = Caja::where('activa', true)->first();

        if ($cajaExistente) {
            return response()->json([
                'message' => 'Ya existe una caja abierta',
                'status' => 400
            ], 400);
        }

        try {
            DB::beginTransaction();

            $caja = Caja::create([
                'fecha_apertura' => Carbon::now(),
                'empleado_id' => $user->persona_id,
                'saldo_inicial' => $request->saldo_inicial,
                'activa' => true
            ]);

            // Registrar el saldo inicial como primera transacción
            $metodoPagoEfectivo = DB::table('metodos_pago')->where('nombre', 'efectivo')->first();
            
            Transaccion::create([
                'caja_id' => $caja->id,
                'tipo' => 'saldo_inicial',
                'monto' => $request->saldo_inicial,
                'descripcion' => 'Saldo inicial de caja',
                'metodo_pago_id' => $metodoPagoEfectivo->id
            ]);

            $transaccionesPendientes = Transaccion::where('tipo', 'turno')->whereNull('caja_id')->get();
            $devolucionesPendientes = Transaccion::where('tipo', 'devolucion')->whereNull('caja_id')->get();
            
            foreach ($transaccionesPendientes as $transaccion) {
                $transaccion->caja_id = $caja->id;
                $transaccion->save();
            }

            DB::commit();

            return response()->json([
                'message' => 'Caja abierta correctamente',
                'status' => 200
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al abrir la caja',
                'error' => $e->getMessage(),
                'status' => 500
            ], 500);
        }
    }

    public function cerrarCaja(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'saldo_final' => 'required|numeric|min:0',
            'observaciones' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error en la validación',
                'errors' => $validator->errors(),
                'status' => 400
            ], 400);
        }

        try {
            DB::beginTransaction();

            $caja = Caja::where('activa', true)->first();

            if (!$caja) {
                throw new \Exception('No hay caja abierta');
            }

            // Registrar el cierre
            $caja->update([
                'fecha_cierre' => Carbon::now(),
                'saldo_final' => $request->saldo_final,
                'activa' => false,
                'observaciones' => $request->observaciones ?? null
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Caja cerrada correctamente',
                'status' => 200
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al cerrar la caja',
                'error' => $e->getMessage(),
                'status' => 500
            ], 500);
        }
    }

    public function index(Request $request)
    {
        $perPage = $request->query('limit', 5);
        $sortBy = $request->query('sortBy', 'fecha_cierre');
        $order = $request->query('order', 'desc');
        $page = $request->query('page', 1);
        $fechaDesde = $request->query('fecha_desde');
        $fechaHasta = $request->query('fecha_hasta');
        $dni = $request->query('dni');

        try {
            $query = Caja::with(['empleado', 'transacciones.metodoPago'])
                ->whereNotNull('fecha_cierre')
                ->join('personas', 'cajas.empleado_id', '=', 'personas.id');

            // Filtro por DNI
            if ($dni) {
                $query->where('personas.dni', 'like', '%' . $dni . '%');
            }

            // Filtro por fecha
            if ($fechaDesde) {
                $query->whereDate('cajas.fecha_cierre', '>=', $fechaDesde);
            }
            if ($fechaHasta) {
                $query->whereDate('cajas.fecha_cierre', '<=', $fechaHasta);
            }

            $query->orderBy("cajas.$sortBy", $order);
            $query->select('cajas.*'); // Asegurarnos de solo seleccionar campos de la tabla cajas

            $cierres = $query->paginate($perPage);

            $cierresFormateados = $cierres->map(function ($cierre) {
                // Calcular totales por método de pago
                $resumenPagos = [
                    'efectivo' => 0,
                    'transferencia' => 0,
                    'tarjeta' => 0,
                    'mercadopago' => 0
                ];

                $balanceTotal = 0;
                $efectivoEnSistema = 0;

                foreach ($cierre->transacciones as $transaccion) {
                    $monto = $transaccion->monto;
                    $balanceTotal += $monto;
                    
                    // Asegurarse que metodoPago no sea null antes de acceder a su nombre
                    if ($transaccion->metodoPago) {
                        $metodoPago = strtolower($transaccion->metodoPago->nombre);
                        if (array_key_exists($metodoPago, $resumenPagos)) {
                            $resumenPagos[$metodoPago] += $monto;
                        } else {
                            // Opcional: Log para métodos no esperados
                            Log::warning("CajaService->index: Método de pago '{$metodoPago}' no listado en resumen para cierre ID: {$cierre->id}");
                        }

                        if ($metodoPago === 'efectivo') {
                            $efectivoEnSistema += $monto;
                        }
                    } else {
                        Log::warning("CajaService->index: Transacción ID {$transaccion->id} no tiene metodoPago asociado para cierre ID: {$cierre->id}");
                    }
                }

                // Calcular la diferencia entre el efectivo en sistema y el efectivo contado
                $diferencia = $cierre->saldo_final - $efectivoEnSistema;

                return [
                    'id' => $cierre->id,
                    'fecha_apertura' => $cierre->fecha_apertura,
                    'fecha_cierre' => $cierre->fecha_cierre,
                    'saldo_inicial' => $cierre->saldo_inicial,
                    'efectivo_contado' => $cierre->saldo_final, // Efectivo contado físicamente
                    'efectivo_en_sistema' => $efectivoEnSistema, // Suma de todas las transacciones en efectivo
                    'diferencia' => $diferencia,
                    'balance_total' => $balanceTotal,
                    'balance_electronico' => $balanceTotal - $efectivoEnSistema,
                    'observaciones' => $cierre->observaciones,
                    'operador' => [
                        'id' => $cierre->empleado->id,
                        'name' => $cierre->empleado->name,
                        'dni' => $cierre->empleado->dni
                    ],
                    'resumen_pagos' => $resumenPagos
                ];
            });

            return response()->json([
                'status' => 200,
                'cierres' => $cierresFormateados,
                'total' => $cierres->total(),
                'currentPage' => $cierres->currentPage(),
                'totalPages' => $cierres->lastPage(),
                'perPage' => $cierres->perPage()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener los cierres de caja: ' . $e->getMessage(),
                'status' => 500
            ], 500);
        }
    }
}
