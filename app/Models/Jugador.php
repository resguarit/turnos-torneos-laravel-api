<?php
// app/Models/Jugador.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Jugador extends Model
{
    use HasFactory;
    
    protected $table = 'jugadores';

    protected $fillable = ['nombre', 'apellido', 'dni', 'telefono', 'fecha_nacimiento'];

    public function equipos()
    {
        return $this->belongsToMany(Equipo::class, 'equipo_jugador')
            ->withPivot('capitan');
    }
}