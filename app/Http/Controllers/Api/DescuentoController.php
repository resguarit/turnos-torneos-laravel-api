<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Horario;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Models\Cancha;
use App\Models\Descuento;

class DescuentoController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $descuento = Descuento::find($id);

        if (!$descuento) {
            return response()->json(['message' => 'Descuento no encontrado'], 404);
        }

        $descuento->delete();

        return response()->json([
            'message' => 'Descuento eliminado correctamente'
        ], 200);
    }

    public function getDescuentos(Request $request)
    {
        $fechaHoy = Carbon::now()->format('Y-m-d');

        $descuentos = Descuento::with('cancha', 'horario')
            ->where('fecha', '>=', $fechaHoy)
            ->orderBy('fecha', 'asc')
            ->get();

        return response()->json([
            'message' => 'Descuentos obtenidos correctamente', 
            'data' => $descuentos
        ], 200);
    }

    public function storeBatch(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'fecha' => 'required|date',
            'deporte_ids' => 'required|array',
            'deporte_ids.*' => 'exists:deportes,id',
            'cancha_ids' => 'required|array',
            'cancha_ids.*' => 'exists:canchas,id',
            'tipo' => 'required|in:porcentaje,fijo',
            'valor' => 'required|numeric|min:0',
            'motivo' => 'nullable|string',
            'hora_inicio' => 'required|date_format:H:i',
            'hora_fin' => [
                'required',
                'date_format:H:i',
                function ($attribute, $value, $fail) use ($request) {
                    $horaInicio = $request->input('hora_inicio');
                    
                    if (!$horaInicio) return;
                    
                    // Convertir las horas a minutos para facilitar la comparación
                    $minutosInicio = $this->horaAMinutos($horaInicio);
                    $minutosFin = $this->horaAMinutos($value);
                    
                    // Permitir que hora_fin sea "00:00" (medianoche = 1440 minutos)
                    if ($value === '00:00') {
                        $minutosFin = 1440; // 24 * 60 = 1440 minutos
                    }
                    
                    // Validar que la hora de fin sea mayor que la de inicio
                    if ($minutosFin <= $minutosInicio) {
                        $fail('La hora de fin debe ser posterior a la hora de inicio, o 00:00 para indicar medianoche.');
                    }
                }
            ],
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        $canchasValidas = Cancha::whereIn('id', $request->cancha_ids)
            ->whereIn('deporte_id', $request->deporte_ids)
            ->get();

        if ($canchasValidas->isEmpty()) {
            return response()->json(['message' => 'No hay canchas válidas para los deportes seleccionados'], 400);
        }

        $fecha = Carbon::createFromFormat('Y-m-d', $request->fecha);
        $dia = $this->getNombreDiaSemana($fecha->dayOfWeek);

        $horarios = Horario::whereIn('deporte_id', $request->deporte_ids)
            ->where('dia', $dia)
            ->where('activo', true)
            ->where(function($query) use ($request) {
                $query->where('hora_inicio', '>=', $request->hora_inicio)
                    ->where('hora_fin', '<=', $request->hora_fin);
            })
            ->get();

        if ($horarios->isEmpty()) {
            return response()->json(['message' => 'No se encontraron horarios para el día y horario especificados'], 400);
        }

        try {
            DB::beginTransaction();

            $descuentos = [];
            foreach ($canchasValidas as $cancha) {
                $horarioCancha = $horarios->where('deporte_id', $cancha->deporte_id);

                foreach ($horarioCancha as $horario) {

                    if ($horario->hora_fin == '00:00:00') {
                        if ($request->hora_fin < '23:30') {
                            continue;
                        }
                    }

                    $descuento = Descuento::updateOrCreate(
                        [
                            'fecha' => $request->fecha,
                            'cancha_id' => $cancha->id,
                            'horario_id' => $horario->id,
                        ],
                        [
                            'tipo' => $request->tipo,
                            'valor' => $request->valor,
                            'motivo' => $request->motivo,
                        ]
                    );
                    $descuentos[] = $descuento;
                }
            }

            DB::commit();
            return response()->json([
                'message' => 'Descuentos aplicados correctamente',
                'descuentos' => $descuentos
            ], 201);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'message' => 'Error al aplicar los descuentos',
                'error' => $e->getMessage()
            ], 500);
        } 

    }

    private function getNombreDiaSemana($diaSemana)
    {
        $dias = [
            0 => 'domingo',
            1 => 'lunes',
            2 => 'martes',
            3 => 'miércoles',
            4 => 'jueves',
            5 => 'viernes',
            6 => 'sábado'
        ];

        return $dias[$diaSemana];
    }

    private function horaAMinutos($hora)
    {
        list($horas, $minutos) = explode(':', $hora);
        return (int)$horas * 60 + (int)$minutos;
    }
}
