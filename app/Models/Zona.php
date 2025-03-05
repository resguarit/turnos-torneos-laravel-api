<?php
// app/Models/Zona.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Zona extends Model
{
    use HasFactory;

    protected $fillable = ['nombre', 'formato', 'año'];

    public function fechas()
    {
        return $this->hasMany(Fecha::class);
    }

    public function equipos()
    {
        return $this->belongsToMany(Equipo::class);
    }
}