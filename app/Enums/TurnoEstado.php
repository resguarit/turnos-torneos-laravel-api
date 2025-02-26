<?php

namespace App\Enums;

enum TurnoEstado: string
{
    case PENDIENTE = 'Pendiente';
    case SEÑADO = 'Señado';
    case PAGADO = 'Pagado';
    case CANCELADO = 'Cancelado';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
