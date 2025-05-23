<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BloqueoDisponibilidadTurno extends Model
{
    use HasFactory;

    protected $table = 'bloqueo_disponibilidad_turnos';

    protected $fillable = [
        'fecha',
        'cancha_id',
        'horario_id'
    ];

    protected $casts = [
        'fecha' => 'date'
    ];

    public function cancha()
    {
        return $this->belongsTo(Cancha::class);
    }

    public function horario()
    {
        return $this->belongsTo(Horario::class);
    }
} 