<?php

namespace App\Enums;

enum PartidoEstado: string
{
    case PENDIENTE = 'Pendiente';
    case FINALIZADO = 'Finalizado';
    case SUSPENDIDO = 'Suspendido';
    case EN_CURSO = 'En Curso';
    case CANCELADO = 'Cancelado';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
