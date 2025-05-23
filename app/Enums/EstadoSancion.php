<?php

namespace App\Enums;

enum EstadoSancion: string
{
    case ACTIVA = 'activa';
    case CUMPLIDA = 'cumplida';
    case APELADA = 'apelada';
    case ANULADA = 'anulada';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}