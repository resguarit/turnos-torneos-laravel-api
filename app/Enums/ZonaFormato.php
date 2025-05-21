<?php
// app/Enums/ZonaFormato.php

namespace App\Enums;

enum ZonaFormato: string
{
    case LIGA = 'Liga';
    case ELIMINATORIA = 'Eliminatoria';
    case GRUPOS = 'Grupos';
    case LIGA_PLAYOFF = 'Liga + Playoff';
    case LIGA_IDA_VUELTA = 'Liga Ida y Vuelta';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}