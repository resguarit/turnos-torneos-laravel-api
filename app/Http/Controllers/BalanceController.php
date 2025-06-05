<?php

namespace App\Http\Controllers;

use App\Models\Caja;
use App\Models\Transaccion;
use App\Models\TipoGasto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class BalanceController extends Controller
{
    /**
     * Obtener el balance entre fechas.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getBalance(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'fecha_desde' => 'required|date',
                'fecha_hasta' => 'required|date|after_or_equal:fecha_desde'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error de validación',
                    'errors' => $validator->errors()
                ], 422);
            }

            $fechaDesde = $request->fecha_desde . ' 00:00:00';
            $fechaHasta = $request->fecha_hasta . ' 23:59:59';

            // Log para depuración
            Log::info("Consultando balance desde {$fechaDesde} hasta {$fechaHasta}");

            // Obtener todas las transacciones en el rango de fechas
            $transacciones = Transaccion::whereBetween('created_at', [$fechaDesde, $fechaHasta])
                ->with('tipoGasto')
                ->get();
            
            // Log para depuración
            Log::info("Encontradas " . count($transacciones) . " transacciones");

            if ($transacciones->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'message' => 'No hay transacciones en el período seleccionado',
                    'balance' => null
                ]);
            }

            // Excluir transacciones de tipo "saldo" para el cálculo del balance
            $transaccionesSinSaldo = $transacciones->filter(function ($transaccion) {
                return $transaccion->tipo !== 'saldo';
            });

            // Calcular ingresos y egresos
            $ingresosTotales = $transaccionesSinSaldo->where('monto', '>', 0)->sum('monto');
            $egresosTotales = abs($transaccionesSinSaldo->where('monto', '<', 0)->sum('monto'));
            $balanceNeto = $ingresosTotales - $egresosTotales;

            // Filtrar todas las transacciones positivas (ingresos)
            $transaccionesIngreso = $transaccionesSinSaldo->where('monto', '>', 0);
            
            // Pago de turnos: Todas las transacciones relacionadas con turnos (tipo turno, seña o pago)
            $pagosTurnos = $transaccionesIngreso
                ->filter(function($transaccion) {
                    return $transaccion->turno_id !== null && 
                           in_array($transaccion->tipo, ['turno', 'seña', 'pago']);
                })
                ->sum('monto');

            // Otras transacciones de ingreso: Todos los demás ingresos
            $otrasTransaccionesIngreso = $transaccionesIngreso
                ->filter(function($transaccion) {
                    return $transaccion->turno_id === null || 
                           !in_array($transaccion->tipo, ['turno', 'seña', 'pago']);
                })
                ->sum('monto');

            // Verificación para asegurar que la suma de desglose sea igual al total
            $totalIngresos = $pagosTurnos + $otrasTransaccionesIngreso;
            if (abs($totalIngresos - $ingresosTotales) > 0.01) {
                Log::warning("Diferencia en cálculo de ingresos: Total $ingresosTotales vs Desglose $totalIngresos");
            }

            // Filtrar todas las transacciones negativas (egresos)
            $transaccionesEgreso = $transaccionesSinSaldo->where('monto', '<', 0);
            
            // Devoluciones: Transacciones de tipo 'devolucion'
            $devoluciones = abs($transaccionesEgreso
                ->where('tipo', 'devolucion')
                ->sum('monto'));

            // Gastos: Transacciones de tipo 'gasto'
            $gastos = abs($transaccionesEgreso
                ->where('tipo', 'gasto')
                ->sum('monto'));

            // Otras transacciones de egreso: Todos los demás egresos
            $otrasTransaccionesEgreso = abs($transaccionesEgreso
                ->whereNotIn('tipo', ['devolucion', 'gasto'])
                ->sum('monto'));

            // Verificación para asegurar que la suma de desglose sea igual al total
            $totalEgresos = $devoluciones + $gastos + $otrasTransaccionesEgreso;
            if (abs($totalEgresos - $egresosTotales) > 0.01) {
                Log::warning("Diferencia en cálculo de egresos: Total $egresosTotales vs Desglose $totalEgresos");
            }

            // Desglose de gastos por categoría
            $gastosPorCategoria = [];
            if ($gastos > 0) {
                // Agrupar gastos por tipo_gasto_id
                $gastosPorTipo = $transaccionesEgreso
                    ->where('tipo', 'gasto')
                    ->groupBy('tipo_gasto_id');
                
                // Procesar gastos con tipo de gasto asignado
                foreach ($gastosPorTipo as $tipoGastoId => $items) {
                    if ($tipoGastoId !== null) {
                        $tipoGasto = $items->first()->tipoGasto;
                        $gastosPorCategoria[] = [
                            'tipo_gasto_id' => $tipoGastoId,
                            'tipo_gasto' => $tipoGasto ? $tipoGasto->nombre : 'Sin categoría',
                            'monto' => abs($items->sum('monto'))
                        ];
                    }
                }
                
                // Agregar gastos sin categoría si existen
                $gastosSinCategoria = abs($transaccionesEgreso
                    ->where('tipo', 'gasto')
                    ->whereNull('tipo_gasto_id')
                    ->sum('monto'));

                if ($gastosSinCategoria > 0) {
                    $gastosPorCategoria[] = [
                        'tipo_gasto_id' => null,
                        'tipo_gasto' => 'Sin categoría',
                        'monto' => $gastosSinCategoria
                    ];
                }
            }

            // Armar la respuesta
            $balance = [
                'ingresos_totales' => $ingresosTotales,
                'egresos_totales' => $egresosTotales,
                'balance_neto' => $balanceNeto,
                'desglose_ingresos' => [
                    'pagos_turnos' => $pagosTurnos,
                    'senas_turnos' => 0, // Ahora está incluido en pagos_turnos
                    'otras_transacciones' => $otrasTransaccionesIngreso
                ],
                'desglose_egresos' => [
                    'devoluciones' => $devoluciones,
                    'gastos' => $gastos,
                    'otras_transacciones' => $otrasTransaccionesEgreso
                ],
                'desglose_gastos' => $gastosPorCategoria
            ];

            return response()->json([
                'success' => true,
                'balance' => $balance
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener el balance: ' . $e->getMessage()
            ], 500);
        }
    }
} 