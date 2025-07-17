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
        'cancha_ids',
        'horario_ids',
        'cupo_maximo',
        'precio_mensual',
        'activa',
        'tipo',
        'duracion'
    ];

    protected $casts = [
        'horario_ids' => 'array',
        'cancha_ids' => 'array',
    ];
    
    public function profesor()
    {
        return $this->belongsTo(Profesor::class, 'profesor_id');
    }

    public function canchas()
    {
        return Cancha::whereIn('id', $this->cancha_ids ?? [])->get();
    }

    // Mantener compatibilidad con código existente
    public function cancha()
    {
        $canchas = $this->canchas();
        return $canchas->isNotEmpty() ? $canchas->first() : null;
    }


    public function getHorariosAttribute()
    {
        return \App\Models\Horario::whereIn('id', $this->horario_ids ?? [])->get();
    }

        public function getCanchasAttribute()
    {
        return Cancha::whereIn('id', $this->cancha_ids ?? [])->get();
    }
}