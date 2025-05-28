<?php

use App\Helpers\DateHelper;

if (!function_exists('formatearFechaCompleta')) {
    /**
     * Formatea fecha completa con día de la semana
     * @param mixed $fecha
     * @return string
     */
    function formatearFechaCompleta($fecha)
    {
        return DateHelper::formatearFechaCompleta($fecha);
    }
}

if (!function_exists('formatearFechaSinDia')) {
    /**
     * Formatea fecha sin día de la semana
     * @param mixed $fecha
     * @return string
     */
    function formatearFechaSinDia($fecha)
    {
        return DateHelper::formatearFechaSinDia($fecha);
    }
}

if (!function_exists('formatearFechaCorta')) {
    /**
     * Formatea fecha corta
     * @param mixed $fecha
     * @return string
     */
    function formatearFechaCorta($fecha)
    {
        return DateHelper::formatearFechaCorta($fecha);
    }
}

if (!function_exists('formatearMonto')) {
    /**
     * Formatea monto con separadores
     * @param mixed $monto
     * @return string
     */
    function formatearMonto($monto)
    {
        return DateHelper::formatearMonto($monto);
    }
}

if (!function_exists('formatearRangoHorario')) {
    /**
     * Formatea rango de horarios
     * @param string $horaInicio
     * @param string $horaFin
     * @return string
     */
    function formatearRangoHorario($horaInicio, $horaFin)
    {
        return DateHelper::formatearRangoHorario($horaInicio, $horaFin);
    }
}

if (!function_exists('calcularDuracion')) {
    /**
     * Calcula duración entre horarios
     * @param string $horaInicio
     * @param string $horaFin
     * @return string
     */
    function calcularDuracion($horaInicio, $horaFin)
    {
        return DateHelper::calcularDuracion($horaInicio, $horaFin);
    }
} 