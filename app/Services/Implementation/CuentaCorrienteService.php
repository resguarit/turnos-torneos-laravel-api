<?php

namespace App\Services\Implementation;

use App\Models\CuentaCorriente;
use App\Models\Transaccion;
use App\Models\Persona;
use App\Models\User;
use App\Services\Interface\CuentaCorrienteServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class CuentaCorrienteService implements CuentaCorrienteServiceInterface
{
    public function getCuentaCorrienteByPersona($personaId)
    {
        try {
            $persona = Persona::with('cuentaCorriente.transacciones')->findOrFail($personaId);
            
            // Verificar si la persona tiene cuenta corriente
            if (!$persona->cuentaCorriente) {
                return [
                    'message' => 'La persona no tiene una cuenta corriente',
                    'status' => 404
                ];
            }
            
            return [
                'persona' => [
                    'id' => $persona->id,
                    'nombre' => $persona->name,
                    'dni' => $persona->dni,
                    'telefono' => $persona->telefono,
                    'direccion' => $persona->direccion,
                ],
                'cuenta_corriente' => [
                    'id' => $persona->cuentaCorriente->id,
                    'saldo' => $persona->cuentaCorriente->saldo,
                    'transacciones' => $persona->cuentaCorriente->transacciones
                ],
                'status' => 200
            ];
        } catch (ModelNotFoundException $e) {
            return [
                'message' => 'Persona no encontrada',
                'status' => 404
            ];
        } catch (\Exception $e) {
            return [
                'message' => 'Error al obtener la cuenta corriente: ' . $e->getMessage(),
                'status' => 500
            ];
        }
    }

    public function getCuentasCorrientes(Request $request)
    {   

        try {
            $perPage = $request->query('limit', 10);
            $sortBy = $request->query('sortBy', 'created_at');
            $order = $request->query('order', 'desc');
            $page = $request->query('page', 1);
            $searchType = $request->query('searchType');
            $searchTerm = $request->query('searchTerm');

            // Consulta base para cuentas corrientes con personas
            $query = CuentaCorriente::with('persona');

            // Filtrar por término de búsqueda si se proporciona
            if ($searchType && $searchTerm) {
                $query->whereHas('persona', function ($q) use ($searchType, $searchTerm) {
                    $q->where($searchType, 'like', "%{$searchTerm}%");
                });
            }

            

            // Ordenar y paginar
            $cuentasCorrientes = $query->orderBy($sortBy, $order)
                ->paginate($perPage, ['*'], 'page', $page);

            return [
                'cuentas_corrientes' => $cuentasCorrientes,
                'status' => 200,
                'total' => $cuentasCorrientes->total(),
                'totalPages' => $cuentasCorrientes->lastPage(),
                'currentPage' => $cuentasCorrientes->currentPage(),
                'perPage' => $cuentasCorrientes->perPage(),
            ];
        } catch (\Exception $e) {
            return [
                'message' => 'Error al obtener las cuentas corrientes: ' . $e->getMessage(),
                'status' => 500
            ];
        }
    }

    public function getMiCuentaCorriente()
    {
        $user = Auth::user();
        
        if (!$user->persona_id) {
            return [
                'message' => 'El usuario no tiene un perfil personal asociado',
                'status' => 404
            ];
        }

        try {
            $persona = Persona::with('cuentaCorriente.transacciones')->findOrFail($user->persona_id);
            
            // Verificar si la persona tiene cuenta corriente
            if (!$persona->cuentaCorriente) {
                return [
                    'message' => 'No tienes una cuenta corriente',
                    'status' => 404
                ];
            }

            return [
                'cuenta_corriente' => [
                    'id' => $persona->cuentaCorriente->id,
                    'saldo' => $persona->cuentaCorriente->saldo,
                    'transacciones' => $persona->cuentaCorriente->transacciones->map(function($transaccion) {
                        return [
                            'id' => $transaccion->id,
                            'monto' => $transaccion->monto,
                            'tipo' => $transaccion->tipo,
                            'descripcion' => $transaccion->descripcion,
                            'fecha' => $transaccion->created_at,
                        ];
                    })
                ],
                'status' => 200
            ];
        } catch (ModelNotFoundException $e) {
            return [
                'message' => 'Persona no encontrada',
                'status' => 404
            ];
        } catch (\Exception $e) {
            return [
                'message' => 'Error al obtener la cuenta corriente: ' . $e->getMessage(),
                'status' => 500
            ];
        }
    }

    public function agregarTransaccion(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'persona_id' => 'required|exists:personas,id',
            'monto' => 'required|numeric', // Esto ya permite valores negativos
            'tipo' => 'required|in:ingreso,egreso,turno,pago,adelanto,devolucion',
            'descripcion' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return [
                'message' => 'Error de validación',
                'errors' => $validator->errors(),
                'status' => 400
            ];
        }

        DB::beginTransaction();

        try {
            $persona = Persona::with('cuentaCorriente')->findOrFail($request->persona_id);
            
            // Si la persona no tiene cuenta corriente, crearla
            if (!$persona->cuentaCorriente) {
                $cuentaCorriente = new CuentaCorriente([
                    'persona_id' => $persona->id,
                    'saldo' => 0
                ]);
                $cuentaCorriente->save();
                $persona->refresh();
            }
            
            // Usar el monto tal como viene, sin ajustes
            $monto = $request->monto;

            // Crear la transacción
            $transaccion = new Transaccion([
                'cuenta_corriente_id' => $persona->cuentaCorriente->id,
                'monto' => $monto,
                'tipo' => $request->tipo,
                'descripcion' => $request->descripcion
            ]);
            $transaccion->save();

            // Actualizar el saldo de la cuenta corriente
            $persona->cuentaCorriente->saldo += $monto;
            $persona->cuentaCorriente->save();

            DB::commit();

            return [
                'message' => 'Transacción agregada con éxito',
                'transaccion' => $transaccion,
                'nuevo_saldo' => $persona->cuentaCorriente->saldo,
                'status' => 201
            ];
        } catch (ModelNotFoundException $e) {
            DB::rollBack();
            return [
                'message' => 'Persona no encontrada',
                'status' => 404
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            return [
                'message' => 'Error al agregar la transacción: ' . $e->getMessage(),
                'status' => 500
            ];
        }
    }
}