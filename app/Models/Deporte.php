<?php
// app/Models/Deporte.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Deporte extends Model
{
    use HasFactory;

    protected $fillable = ['nombre', 'jugadores_por_equipo', 'duracion_turno'];

    public function torneos()
    {
        return $this->hasMany(Torneo::class);
    }

    public function canchas()
    {
        return $this->hasMany(Cancha::class);
    }

    public function horarios()
    {
        return $this->hasMany(Horario::class);
    }
}