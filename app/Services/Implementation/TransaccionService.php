<?php

namespace App\Services\Implementation;

use App\Models\Transaccion;
use App\Services\Interface\TransaccionServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Validator;
use App\Models\CuentaCorriente;
use App\Models\Persona;
use App\Models\Turno;
use App\Enums\TurnoEstado;

class TransaccionService implements TransaccionServiceInterface
{
    public function getTransacciones(Request $request)
    {
        $limit = $request->limit ?? 10;
        $sortBy = $request->sortBy ?? 'created_at';
        $order = $request->order ?? 'desc';
        $searchTerm = $request->searchTerm ?? '';
        $searchType = $request->searchType ?? '';

        $query = Transaccion::with(['cuentaCorriente.persona', 'turno']);

        // Aplicar filtros si hay término de búsqueda
        if (!empty($searchTerm)) {
            if ($searchType == 'description') {
                $query->where('descripcion', 'like', '%' . $searchTerm . '%');
            } elseif ($searchType == 'type') {
                $query->where('tipo', 'like', '%' . $searchTerm . '%');
            } elseif ($searchType == 'person') {
                $query->whereHas('cuentaCorriente.persona', function ($q) use ($searchTerm) {
                    $q->where('nombre', 'like', '%' . $searchTerm . '%')
                      ->orWhere('apellido', 'like', '%' . $searchTerm . '%')
                      ->orWhere('dni', 'like', '%' . $searchTerm . '%');
                });
            } elseif ($searchType == 'turno') {
                $query->whereHas('turno', function ($q) use ($searchTerm) {
                    $q->where('id', $searchTerm);
                });
            }
        }

        // Ordenar y paginar
        $transacciones = $query->orderBy($sortBy, $order)->paginate($limit);

        return response()->json([
            'transacciones' => $transacciones,
            'success' => true
        ]);
    }

    public function storeTransaccion(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'persona_id' => 'sometimes|exists:personas,id',
            'cuenta_corriente_id' => 'sometimes|exists:cuentas_corrientes,id',
            'turno_id' => 'nullable|exists:turnos,id',
            'monto' => 'required|numeric',
            'tipo' => 'required|in:ingreso,egreso,seña,turno,pago,deuda,devolucion',
            'descripcion' => 'nullable|string',
            'caja_id' => 'nullable|exists:cajas,id',
            'metodo_pago' => 'required|in:efectivo,transferencia,tarjeta,mercadopago',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors(),
                'message' => 'Error de validación',
                'status' => 400
            ], 400);
        }

        DB::beginTransaction();

        try {
            $metodoPago = DB::table('metodos_pago')->where('nombre', $request->metodo_pago)->first();
            
            // Preparar los datos de la transacción
            $transaccionData = [
                'monto' => $request->monto,
                'tipo' => $request->tipo,
                'descripcion' => $request->descripcion ?? null,
                'caja_id' => $request->caja_id,
                'metodo_pago_id' => $metodoPago->id,
            ];

            // Si es una transacción de cuenta corriente
            if ($request->has('cuenta_corriente_id') || $request->has('persona_id')) {
                $cuentaCorriente = null;

                if ($request->has('cuenta_corriente_id')) {
                    $cuentaCorriente = CuentaCorriente::findOrFail($request->cuenta_corriente_id);
                    $persona = $cuentaCorriente->persona;
                } elseif ($request->has('persona_id')) {
                    $persona = Persona::with('cuentaCorriente')->findOrFail($request->persona_id);
                
                    if (!$persona->cuentaCorriente) {
                        $cuentaCorriente = CuentaCorriente::create([
                            'persona_id' => $persona->id,
                            'saldo' => 0,
                        ]);
                    } else {
                        $cuentaCorriente = $persona->cuentaCorriente;
                    }
                }

                $transaccionData['cuenta_corriente_id'] = $cuentaCorriente->id;
            }

            // Agregar el turno_id si está presente
            if ($request->has('turno_id')) {
                $turno = Turno::findOrFail($request->turno_id);
                $transaccionData['turno_id'] = $turno->id;
                
                // Si no hay descripción y hay turno, generar una descripción automática
                if (empty($transaccionData['descripcion'])) {
                    $accion = match($request->tipo) {
                        'seña' => 'Pago de seña',
                        'pago' => 'Pago',
                        'devolucion' => 'Devolución',
                        default => 'Transacción'
                    };
                    $transaccionData['descripcion'] = "{$accion} por turno #{$turno->id}";
                }
            }

            $transaccion = Transaccion::create($transaccionData);
            $transaccion->save();

            // Actualizar el saldo de la cuenta corriente solo si existe
            if (isset($cuentaCorriente)) {
                $cuentaCorriente->saldo += $request->monto;
                $cuentaCorriente->save();
            }

            DB::commit();

            return response()->json([
                'message' => 'Transacción creada con éxito',
                'transaccion' => $transaccion,
                'success' => true,
                'status' => 201
            ], 201);

        } catch (ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'message' => $request->has('cuenta_corriente_id') 
                    ? 'Cuenta corriente no encontrada' 
                    : ($request->has('turno_id') ? 'Turno no encontrado' : 'Persona no encontrada'),
                'error' => $e->getMessage(),
                'success' => false,
                'status' => 404
            ], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al crear la transacción',
                'error' => $e->getMessage(),
                'success' => false,
                'status' => 500
            ], 500);
        }
    }

    public function saldoPorTurno($id)
    {
        $turno = Turno::findOrFail($id);
        $transacciones = Transaccion::where('turno_id', $id)->get();

        $saldo = 0;
        foreach ($transacciones as $transaccion) {
            $saldo += $transaccion->monto;
        }
        
        return response()->json([
            'transacciones' => $transacciones,
            'saldo' => $saldo,
            'success' => true,
            'status' => 200
        ], 200);
    }
}
