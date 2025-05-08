<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Enums\TipoSancion;
use App\Enums\EstadoSancion;

class Sancion extends Model
{
    use HasFactory;

    protected $table = 'sanciones';

    protected $fillable = [
        'equipo_jugador_id',
        'fecha_sancion',
        'motivo',
        'tipo_sancion',
        'cantidad_fechas',
        'fecha_inicio',
        'fecha_fin',
        'partido_id',
        'estado',
    ];

    protected $casts = [
        'tipo_sancion' => TipoSancion::class,
        'estado' => EstadoSancion::class,
    ];

    public function equipoJugador()
    {
        return $this->belongsTo(EquipoJugador::class, 'equipo_jugador_id');
    }

    // Relación con la tabla fechas (fecha de inicio)
    public function fechaInicio()
    {
        return $this->belongsTo(Fecha::class, 'fecha_inicio');
    }

    // Relación con la tabla fechas (fecha de fin)
    public function fechaFin()
    {
        return $this->belongsTo(Fecha::class, 'fecha_fin');
    }

    // Relación con la tabla partidos
    public function partido()
    {
        return $this->belongsTo(Partido::class, 'partido_id');
    }
}