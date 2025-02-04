<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Turno;
use Carbon\Carbon;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use App\Models\Cancha;
use App\Models\Horario;

class DashboardController extends Controller
{
    public function totalReservas()
    {
        $totalReservasActual = Turno::where('estado', '!=', 'Cancelado')
            ->where('tipo', '!=', 'torneo')
            ->whereYear('fecha_turno', Carbon::now()->year)
            ->whereMonth('fecha_turno', Carbon::now()->month)
            ->count();

        $totalReservasAnterior = Turno::where('estado', '!=', 'Cancelado')
            ->where('tipo', '!=', 'torneo')
            ->whereYear('fecha_turno', Carbon::now()->subMonth()->year)
            ->whereMonth('fecha_turno', Carbon::now()->subMonth()->month)
            ->count();

        if ($totalReservasAnterior > 0) {
            $cambio = (($totalReservasActual - $totalReservasAnterior) / $totalReservasAnterior) * 100;
        } else {
            $cambio = $totalReservasActual * 100; 
        }

        $tendencia = $cambio > 0 ? 'subida' : ($cambio < 0 ? 'bajada' : 'neutral');

        $response = [
            'total_reservas' => $totalReservasActual,
            'cambio' => number_format(abs($cambio), 2),
            'tendencia' => $tendencia
        ];

        return response()->json($response, 200);
    }

    public function usuariosActivos()
    {
        $usuariosActivosActual = User::whereHas('turnos', function ($query) {
            $query->where('estado', '!=', 'Cancelada')
                  ->whereYear('fecha_turno', Carbon::now()->year)
                  ->whereMonth('fecha_turno', Carbon::now()->month);
        })->count();

        $usuariosActivosAnterior = User::whereHas('turnos', function ($query) {
            $query->where('estado', '!=', 'Cancelada')
                  ->whereYear('fecha_turno', Carbon::now()->subMonth()->year)
                  ->whereMonth('fecha_turno', Carbon::now()->subMonth()->month);
        })->count();

        if ($usuariosActivosAnterior > 0) {
            $cambio = (($usuariosActivosActual - $usuariosActivosAnterior) / $usuariosActivosAnterior) * 100;
        } else {
            $cambio = $usuariosActivosActual * 100; 
        }

        $tendencia = $cambio > 0 ? 'subida' : ($cambio < 0 ? 'bajada' : 'neutral');

        $response = [
            'usuarios_activos' => $usuariosActivosActual,
            'cambio' => number_format(abs($cambio), 2),
            'tendencia' => $tendencia
        ];

        return response()->json($response, 200);
    }

    public function ingresos()
    {
        $ingresosActual = Turno::whereYear('fecha_turno', Carbon::now()->year)
            ->whereMonth('fecha_turno', Carbon::now()->month)
            ->where(function ($query) {
                $query->where('estado', 'Pagado')
                      ->orWhere('estado', 'Señado');
            })
            ->sum(DB::raw('CASE WHEN estado = "Pagado" THEN monto_total ELSE monto_seña END'));

        $ingresosAnterior = Turno::whereYear('fecha_turno', Carbon::now()->subMonth()->year)
            ->whereMonth('fecha_turno', Carbon::now()->subMonth()->month)
            ->where(function ($query) {
                $query->where('estado', 'Pagado')
                      ->orWhere('estado', 'Señado');
            })
            ->sum(DB::raw('CASE WHEN estado = "Pagado" THEN monto_total ELSE monto_seña END'));

        if ($ingresosAnterior > 0) {
            $cambio = (($ingresosActual - $ingresosAnterior) / $ingresosAnterior) * 100;
        } else {
            $cambio = $ingresosActual * 100; 
        }

        $tendencia = $cambio > 0 ? 'subida' : ($cambio < 0 ? 'bajada' : 'neutral');

        $response = [
            'ingresos' => number_format($ingresosActual, 2),
            'cambio' => number_format($cambio, 2),
            'tendencia' => $tendencia
        ];

        return response()->json($response, 200);
    }

    public function tasaOcupacion()
    {
        $fechaHoy = Carbon::today()->startOfDay();

        $turnosHoy = Turno::whereDate('fecha_turno', $fechaHoy)
            ->where('estado', '!=', 'Cancelado')
            ->count();

        $canchasCount = Cancha::where('activa', true)->count();

        $diaSemana = $fechaHoy->dayOfWeek;
        $horariosCount = Horario::where('activo', true)
            ->where('dia', $this->getNombreDiaSemana($diaSemana))
            ->count();

        $tasaOcupacionActual = ($turnosHoy / ($canchasCount * $horariosCount)) * 100;

        $fechaAyer = Carbon::yesterday()->startOfDay();
        $turnosAyer = Turno::whereDate('fecha_turno', $fechaAyer)
            ->where('estado', '!=', 'Cancelado')
            ->count();
        $tasaOcupacionAnterior = ($turnosAyer / ($canchasCount * $horariosCount)) * 100;

        if ($tasaOcupacionAnterior > 0) {
            $cambio = (($tasaOcupacionActual - $tasaOcupacionAnterior) / $tasaOcupacionAnterior) * 100;
        } else {
            $cambio = $tasaOcupacionActual * 100; // Si no hay ocupación el día anterior, el cambio es el total actual
        }

        $tendencia = $cambio > 0 ? 'subida' : ($cambio < 0 ? 'bajada' : 'neutral');

        $response = [
            'tasa_ocupacion' => number_format($tasaOcupacionActual, 2),
            'cambio' => number_format(abs($cambio)),
            'tendencia' => $tendencia
        ];

        return response()->json($response, 200);
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

    public function canchaMasPopular()
    {
        $canchaMasPopular = Turno::select('cancha_id', DB::raw('COUNT(*) as total_reservas'))
            ->where('estado', '!=', 'Cancelado')
            ->groupBy('cancha_id')
            ->orderBy('total_reservas', 'desc')
            ->first();

        if ($canchaMasPopular) {
            $cancha = Cancha::find($canchaMasPopular->cancha_id);
            $response = [
                'cancha_id' => $cancha->id,
                'nro' => $cancha->nro,
                'tipo' => $cancha->tipo_cancha,
                'total_reservas' => $canchaMasPopular->total_reservas
            ];
        } else {
            $response = [
                'message' => 'No se encontraron reservas'
            ];
        }

        return response()->json($response, 200);
    }

    public function horasPico()
    {
        $turnosDelMes = Turno::whereYear('fecha_turno', Carbon::now()->year)
            ->whereMonth('fecha_turno', Carbon::now()->month)
            ->where('estado', '!=', 'Cancelado')
            ->with('horario')
            ->get();

         $horasReservadas = $turnosDelMes->groupBy(function ($turno) {
            return $turno->horario->hora_inicio . ' - ' . $turno->horario->hora_fin;
        })->map(function ($group) {
            return $group->count();
        });

        $horaPico = $horasReservadas->sortDesc()->keys()->first();
        $totalReservas = $horasReservadas->sortDesc()->first();

        $response = [
            'hora_pico' => $horaPico,
            'total_reservas' => $totalReservas
        ];

        return response()->json($response, 200);
    }

    public function reservasPorMes()
    {
        $reservasPorMes = Turno::select(DB::raw('MONTH(fecha_turno) as mes'), DB::raw('COUNT(*) as total_reservas'))
            ->where('estado', '!=', 'Cancelado')
            ->whereYear('fecha_turno', Carbon::now()->year)
            ->groupBy(DB::raw('MONTH(fecha_turno)'))
            ->orderBy(DB::raw('MONTH(fecha_turno)'))
            ->get();

        $meses = [
            1 => 'Ene', 2 => 'Feb', 3 => 'Mar', 4 => 'Abr', 5 => 'May', 6 => 'Jun',
            7 => 'Jul', 8 => 'Ago', 9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dic'
        ];

        $response = collect($meses)->mapWithKeys(function ($nombre, $mes) {
            return [$nombre => 0];
        });

        foreach ($reservasPorMes as $reserva) {
            $nombreMes = $meses[$reserva->mes];
            $response[$nombreMes] = $reserva->total_reservas;
        }

        return response()->json($response, 200);
    }
}
