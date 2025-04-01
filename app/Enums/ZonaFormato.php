<?php
// app/Enums/ZonaFormato.php

namespace App\Enums;

enum ZonaFormato: string
{
    case LIGA = 'Liga';
    case ELIMINATORIA = 'Eliminatoria';
    case GRUPOS = 'Grupos';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}