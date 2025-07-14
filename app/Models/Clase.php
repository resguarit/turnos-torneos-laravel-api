<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Horario;
use App\Models\Cancha;

class Clase extends Model
{
    protected $table = 'clases';

    protected $fillable = [
        'nombre',
        'descripcion',
        'fecha_inicio',
        'fecha_fin',
        'profesor_id',
        'cancha_id',
        'horario_ids',
        'cupo_maximo',
        'precio_mensual',
        'activa',
        'tipo',
        'duracion'
    ];

    protected $casts = [
        'horario_ids' => 'array',
    ];

    public function profesor()
    {
        return $this->belongsTo(Profesor::class, 'profesor_id');
    }

    public function cancha()
    {
        return $this->belongsTo(Cancha::class, 'cancha_id');
    }

    public function getHorariosAttribute()
    {
        return \App\Models\Horario::whereIn('id', $this->horario_ids ?? [])->get();
    }
}