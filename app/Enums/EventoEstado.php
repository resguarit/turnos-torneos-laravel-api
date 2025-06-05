<?php

namespace App\Enums;

enum EventoEstado: string
{
    case DISPONIBLE = 'disponible';
    case RESERVADO = 'reservado';
    case PAGADO = 'pagado';
    case CANCELADO = 'cancelado';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}