<?php
// app/Models/Zona.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes; // Add this import
use App\Enums\ZonaFormato;

class Zona extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = ['nombre', 'formato', 'año', 'torneo_id'];

    protected $casts = [
        'formato' => ZonaFormato::class,
    ];

    public function fechas()
    {
        return $this->hasMany(Fecha::class);
    }

    public function equipos()
    {
        return $this->hasMany(Equipo::class);
    }

    public function grupos()
    {
        return $this->hasMany(Grupo::class);
    }

    public function torneo()
    {
        return $this->belongsTo(Torneo::class);
    }
}