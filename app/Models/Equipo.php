<?php
// app/Models/Equipo.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Equipo extends Model
{
    use HasFactory;

    protected $fillable = ['nombre', 'escudo'];

    public function jugadores()
    {
        return $this->belongsToMany(Jugador::class, 'equipo_jugador')
            ->withPivot('capitan');
    }
    
    public function zonas()
    {
        return $this->belongsToMany(Zona::class, 'equipo_zona');
    }

}