<?php
// app/Models/Deporte.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Deporte extends Model
{
    use HasFactory;

    protected $fillable = ['nombre', 'jugadores_por_equipo'];

    public function torneos()
    {
        return $this->hasMany(Torneo::class);
    }
}