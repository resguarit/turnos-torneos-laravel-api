<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Fecha extends Model
{
    use HasFactory;

    protected $fillable = ['nombre', 'fecha_inicio', 'fecha_fin', 'estado', 'zona_id'];

    public function zona()
    {
        return $this->belongsTo(Zona::class);
    }

    public function partidos()
    {
        return $this->hasMany(Partido::class);
    }
}
