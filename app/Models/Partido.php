<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Enums\PartidoEstado;
use App\Models\Penal;

class Partido extends Model
{
    use HasFactory;

    protected $fillable = ['fecha', 'horario_id', 'cancha_id', 'estado', 'marcador_local', 'marcador_visitante', 'ganador_id', 'fecha_id', 'equipo_local_id', 'equipo_visitante_id'];

    protected $casts = [
        'estado' => PartidoEstado::class,
    ];

    public function fecha()
    {
        return $this->belongsTo(Fecha::class);
    }

    public function equipos()
    {
        return $this->belongsToMany(Equipo::class, 'equipo_partido');
    }

    public function estadisticas()
    {
        return $this->hasMany(Estadistica::class);
    }

    public function horario()
    {
        return $this->belongsTo(Horario::class);
    }

    public function cancha()
    {
        return $this->belongsTo(Cancha::class);
    }

    public function ganador()
    {
        return $this->belongsTo(Equipo::class, 'ganador_id');
    }

    public function equipoLocal()
    {
        return $this->belongsTo(Equipo::class, 'equipo_local_id');
    }

    public function equipoVisitante()
    {
        return $this->belongsTo(Equipo::class, 'equipo_visitante_id');
    }

    public function penales()
    {
        return $this->hasMany(Penal::class, 'partido_id');
    }
}
