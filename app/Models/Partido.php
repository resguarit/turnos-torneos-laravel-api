<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Partido extends Model
{
    use HasFactory;

    protected $fillable = ['fecha', 'horario_id', 'cancha_id', 'estado', 'marcador_local', 'marcador_visitante', 'ganador_id', 'fecha_id'];

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
}
