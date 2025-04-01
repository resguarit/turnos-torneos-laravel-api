<?php

namespace App\Services\Implementation;

use App\Models\Turno;
use App\Models\User;
use App\Models\Cancha;
use App\Models\Horario;
use App\Services\Interface\DashboardServiceInterface;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\Transaccion;

class DashboardService implements DashboardServiceInterface
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

        return response()->json([
            'total_reservas' => $totalReservasActual,
            'cambio' => number_format(abs($cambio), 2),
            'tendencia' => $tendencia
        ], 200);
    }

    public function usuariosActivos()
    {
        // Obtener usuarios activos a través de la relación persona->turnos
        $usuariosActivosActual = User::whereHas('persona.turnos', function ($query) {
            $query->where('estado', '!=', 'Cancelado')
                  ->whereYear('fecha_turno', Carbon::now()->year)
                  ->whereMonth('fecha_turno', Carbon::now()->month);
        })->count();

        // Obtener usuarios activos del mes anterior
        $usuariosActivosAnterior = User::whereHas('persona.turnos', function ($query) {
            $query->where('estado', '!=', 'Cancelado')
                  ->whereYear('fecha_turno', Carbon::now()->subMonth()->year)
                  ->whereMonth('fecha_turno', Carbon::now()->subMonth()->month);
        })->count();

        if ($usuariosActivosAnterior > 0) {
            $cambio = (($usuariosActivosActual - $usuariosActivosAnterior) / $usuariosActivosAnterior) * 100;
        } else {
            $cambio = $usuariosActivosActual * 100;
        }

        $tendencia = $cambio > 0 ? 'subida' : ($cambio < 0 ? 'bajada' : 'neutral');

        return response()->json([
            'usuarios_activos' => $usuariosActivosActual,
            'cambio' => number_format(abs($cambio), 2),
            'tendencia' => $tendencia
        ], 200);
    }

    public function ingresos()
    {
        // Obtener ingresos netos del mes actual (todas las transacciones de caja)
        $ingresosActual = Transaccion::whereYear('created_at', Carbon::now()->year)
            ->whereMonth('created_at', Carbon::now()->month)
            ->whereNotNull('caja_id')  // Solo transacciones registradas en caja
            ->sum('monto');

        // Obtener ingresos netos del mes anterior
        $ingresosAnterior = Transaccion::whereYear('created_at', Carbon::now()->subMonth()->year)
            ->whereMonth('created_at', Carbon::now()->subMonth()->month)
            ->whereNotNull('caja_id')  // Solo transacciones registradas en caja
            ->sum('monto');

        // Calcular el cambio porcentual
        if ($ingresosAnterior != 0) {  // Cambiado a != 0 porque podría ser negativo
            $cambio = (($ingresosActual - $ingresosAnterior) / abs($ingresosAnterior)) * 100;
        } else {
            $cambio = $ingresosActual != 0 ? 100 : 0;
        }

        $tendencia = $cambio > 0 ? 'subida' : ($cambio < 0 ? 'bajada' : 'neutral');

        return response()->json([
            'ingresos' => $ingresosActual,
            'cambio' => number_format(abs($cambio), 2),
            'tendencia' => $tendencia
        ], 200);
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

        if ($turnosHoy == 0 || ($canchasCount * $horariosCount) == 0) {
            $tasaOcupacionActual = 0;
        } else {
            $tasaOcupacionActual = ($turnosHoy / ($canchasCount * $horariosCount)) * 100;
        }

        $fechaAyer = Carbon::yesterday()->startOfDay();
        $turnosAyer = Turno::whereDate('fecha_turno', $fechaAyer)
            ->where('estado', '!=', 'Cancelado')
            ->count();

        if ($turnosAyer == 0 || ($canchasCount * $horariosCount) == 0) {
            $tasaOcupacionAnterior = 0;
        } else {
            $tasaOcupacionAnterior = ($turnosAyer / ($canchasCount * $horariosCount)) * 100;
        }

        if ($tasaOcupacionAnterior > 0) {
            $cambio = (($tasaOcupacionActual - $tasaOcupacionAnterior) / $tasaOcupacionAnterior) * 100;
        } else {
            $cambio = $tasaOcupacionActual * 100;
        }

        $tendencia = $cambio > 0 ? 'subida' : ($cambio < 0 ? 'bajada' : 'neutral');

        return response()->json([
            'tasa_ocupacion' => number_format($tasaOcupacionActual, 2),
            'cambio' => number_format(abs($cambio)),
            'tendencia' => $tendencia
        ], 200);
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
            return response()->json([
                'cancha_id' => $cancha->id,
                'nro' => $cancha->nro,
                'tipo' => $cancha->tipo_cancha,
                'total_reservas' => $canchaMasPopular->total_reservas
            ], 200);
        }

        return response()->json([
            'message' => 'No se encontraron reservas'
        ], 200);
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

        return response()->json([
            'hora_pico' => $horaPico,
            'total_reservas' => $totalReservas
        ], 200);
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
}