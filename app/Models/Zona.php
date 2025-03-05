<?php
// app/Models/Zona.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Zona extends Model
{
    use HasFactory;

    protected $fillable = ['nombre', 'formato', 'año', 'torneo_id'];

    public function fechas()
    {
        return $this->hasMany(Fecha::class);
    }

    public function equipos()
    {
        return $this->hasMany(Equipo::class);
    }

    public function torneo()
    {
        return $this->belongsTo(Torneo::class);
    }
}