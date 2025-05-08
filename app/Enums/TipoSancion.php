<?php

namespace App\Enums;

enum TipoSancion: string
{
    case EXPULSION = 'expulsión';
    case ADVERTENCIA = 'advertencia';
    case SUSPENSION = 'suspensión';
    case MULTA = 'multa';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}