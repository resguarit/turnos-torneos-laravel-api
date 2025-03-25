<?php

namespace App\Enums;

enum FechaEstado: string
{
    case PENDIENTE = 'Pendiente';
    case FINALIZADA = 'Finalizada';
    case SUSPENDIDA = 'Suspendida';
    case EN_CURSO = 'En Curso';
    case CANCELADA = 'Cancelada';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
