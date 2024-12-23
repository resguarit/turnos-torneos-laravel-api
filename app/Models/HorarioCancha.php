<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HorarioCancha extends Model
{
    protected $table = 'horarios_cancha';

    protected $fillable = ['cancha_id', 'horario_id', 'activo'];

    public function cancha(){
        return $this->belongsTo(Cancha::class, 'cancha_id');
    }

    public function horario(){
        return $this->belongsTo(Horario::class, 'horario_id');
    }

}
