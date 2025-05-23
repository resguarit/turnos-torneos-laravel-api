<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Enums\EventoEstado;

class EventoHorarioCancha extends Model
{
    protected $table = 'evento_horario_cancha';

    protected $fillable = [
        'evento_id',
        'horario_id',
        'cancha_id',
        'estado',
    ];

    protected $casts = [
        'estado' => EventoEstado::class,
    ];

    public function evento()
    {
        return $this->belongsTo(Evento::class, 'evento_id');
    }

    public function horario()
    {
        return $this->belongsTo(Horario::class, 'horario_id');
    }

    public function cancha()
    {
        return $this->belongsTo(Cancha::class, 'cancha_id');
    }
}
