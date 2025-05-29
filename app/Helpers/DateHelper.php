<?php

namespace App\Helpers;

use Carbon\Carbon;

class DateHelper
{
    /**
     * Formatea fecha completa con día de la semana
     * Uso: Información principal, fechas destacadas en emails
     * @param Carbon|string $fecha
     * @return string - "lunes, 3 de junio, 2025"
     */
    public static function formatearFechaCompleta($fecha)
    {
        try {
            $carbon = $fecha instanceof Carbon ? $fecha : Carbon::parse($fecha);
            return $carbon->locale('es')->isoFormat('dddd, D [de] MMMM, YYYY');
        } catch (\Exception $e) {
            return $fecha;
        }
    }

    /**
     * Formatea fecha sin día de la semana
     * Uso: Información secundaria en emails
     * @param Carbon|string $fecha
     * @return string - "3 de junio, 2025"
     */
    public static function formatearFechaSinDia($fecha)
    {
        try {
            $carbon = $fecha instanceof Carbon ? $fecha : Carbon::parse($fecha);
            return $carbon->locale('es')->isoFormat('D [de] MMMM, YYYY');
        } catch (\Exception $e) {
            return $fecha;
        }
    }

    /**
     * Formatea fecha corta para listas y tablas
     * Uso: Referencias rápidas, IDs, códigos
     * @param Carbon|string $fecha
     * @return string - "03/06/2025"
     */
    public static function formatearFechaCorta($fecha)
    {
        try {
            $carbon = $fecha instanceof Carbon ? $fecha : Carbon::parse($fecha);
            return $carbon->format('d/m/Y');
        } catch (\Exception $e) {
            return $fecha;
        }
    }

    /**
     * Formatea hora desde fecha
     * @param Carbon|string $fecha
     * @return string - "14:30"
     */
    public static function formatearHora($fecha)
    {
        try {
            $carbon = $fecha instanceof Carbon ? $fecha : Carbon::parse($fecha);
            return $carbon->format('H:i');
        } catch (\Exception $e) {
            return $fecha;
        }
    }

    /**
     * Formatea monto con separadores de miles
     * @param float|string $monto
     * @return string - "1.234,56"
     */
    public static function formatearMonto($monto)
    {
        try {
            return number_format(abs(floatval($monto)), 2, ',', '.');
        } catch (\Exception $e) {
            return $monto;
        }
    }

    /**
     * Calcula la duración en minutos entre dos horarios
     * Maneja el caso especial cuando la hora de fin es 00:00:00 (medianoche)
     * @param string $horaInicio - Hora en formato HH:MM:SS o HH:MM
     * @param string $horaFin - Hora en formato HH:MM:SS o HH:MM
     * @return string - "90 min"
     */
    public static function calcularDuracion($horaInicio, $horaFin)
    {
        if (!$horaInicio || !$horaFin) return '0 min';
        
        try {
            // Extraer horas y minutos del formato HH:MM:SS o HH:MM
            $inicioPartes = explode(':', $horaInicio);
            $finPartes = explode(':', $horaFin);
            
            // Convertir a minutos totales
            $horaInicioMinutos = (int)$inicioPartes[0] * 60 + (int)$inicioPartes[1];
            
            $horaFinMinutos;
            if ($finPartes[0] === '00') {
                // Si la hora de fin es 00:00:00, significa medianoche (24:00)
                $horaFinMinutos = 24 * 60;
            } else {
                $horaFinMinutos = (int)$finPartes[0] * 60 + (int)$finPartes[1];
            }
            
            $duracionMinutos = $horaFinMinutos - $horaInicioMinutos;
            
            return $duracionMinutos . ' min';
        } catch (\Exception $e) {
            return '0 min';
        }
    }

    /**
     * Formatea un rango de horarios
     * @param string $horaInicio
     * @param string $horaFin
     * @return string - "14:30 - 16:00"
     */
    public static function formatearRangoHorario($horaInicio, $horaFin)
    {
        if (!$horaInicio || !$horaFin) return 'Horario no definido';
        
        try {
            $inicio = Carbon::createFromFormat('H:i:s', $horaInicio)->format('H:i');
            $fin = Carbon::createFromFormat('H:i:s', $horaFin)->format('H:i');
            
            return $inicio . ' - ' . $fin;
        } catch (\Exception $e) {
            return 'Horario no definido';
        }
    }
} 