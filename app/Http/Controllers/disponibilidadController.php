<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Reserva;
use App\Models\Cancha;
use App\Models\Horario;

class disponibilidadController extends Controller
{
    public function getHorariosNoDisponibles()
    {

        $fechaInicio = now();
        $fechaFin = now()->addDays(30);

        $canchasCount = Cancha::count();
        $horarios = Horario::all();

        $reservas = Reserva::whereBetween('fecha_turno', [$fechaInicio, $fechaFin])
                            ->with('horarioCancha.horario')
                            ->get();

        $noDisponibles = [];

        foreach ($reservas as $reserva) {
            $fecha = $reserva->fecha_turno->format('Y-m-d');
            $horario = $reserva->horarioCancha->horario;
            $intervalo = $horario->horaInicio . '-' . $horario->horaFin;

            if (!isset($noDisponibles[$fecha])) {
                $noDisponibles[$fecha] = [];
            }

            if (!isset($noDisponibles[$fecha][$intervalo])) {
                $noDisponibles[$fecha][$intervalo] = 0;
            }

            $noDisponibles[$fecha][$intervalo]++;
        }

        $result = [];

        foreach ($noDisponibles as $fecha => $horarios) {
            foreach ($horarios as $intervalo => $count) {
                if ($count >= $canchasCount) {
                    if (!isset($result[$fecha])) {
                        $result[$fecha] = [];
                    }
                    $result[$fecha][] = $intervalo;
                }
            }
        }

        return response()->json($result, 200);
    }
}
