<?php
// app/Models/Torneo.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Torneo extends Model
{
    use HasFactory;

    protected $fillable = ['nombre', 'año', 'deporte_id', 'precio_inscripcion', 'precio_por_fecha'];

    public function deporte()
    {
        return $this->belongsTo(Deporte::class);
    }

    public function zonas()
    {
        return $this->hasMany(Zona::class);
    }
}