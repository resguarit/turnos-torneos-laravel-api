<?php
// app/Models/Equipo.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Equipo extends Model
{
    use HasFactory;

    protected $fillable = ['nombre', 'escudo', 'zona_id'];

    public function jugadores()
    {
        return $this->hasMany(Jugador::class);
    }
    
    public function zona()
    {
        return $this->belongsTo(Zona::class);
    }
}