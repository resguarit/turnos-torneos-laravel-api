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

if (!function_exists('')){
    /**
     * Genera una URL para el frontend de un tenant específico.
     *
     * @param string $subdominio El subdominio del tenant.
     * @param string $path La ruta a la que se quiere apuntar (ej: '/checkout/success').
     * @return string La URL completa.
     */
    function tenant_url(string $subdominio, string $path = ''): string
    {
        $baseDomain = config('app.base_domain', 'rgturnos.com.ar');
        $protocol = config('app.url_protocol', 'https');

        if ($path && $path[0] === '/') {
            $path = substr($path, 1);
        }

        return "{$protocol}://{$subdominio}.{$baseDomain}/{$path}";
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