<?php
// app/Models/Jugador.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Jugador extends Model
{
    use HasFactory;

    protected $fillable = ['nombre', 'apellido', 'dni', 'telefono', 'fecha_nacimiento', 'equipo_id'];

    public function equipo()
    {
        return $this->belongsTo(Equipo::class);
    }
}