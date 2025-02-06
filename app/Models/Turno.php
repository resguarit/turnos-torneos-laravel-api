<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Turno extends Model
{
    use HasFactory;

    protected $table = 'turnos';

    protected $fillable = ['fecha_turno', 'fecha_reserva', 'horario_id', 'cancha_id', 'usuario_id', 'monto_total', 'monto_seña', 'estado', 'tipo'];

    protected $hidden = ['created_at', 'updated_at'];

    
    protected $dates = ['fecha_turno', 'fecha_reserva'];

    public function getFechaTurnoAttribute($value)
    {
        return Carbon::parse($value);
    }

    public function horario(){
        return $this->belongsTo(Horario::class, 'horario_id');
    }

    public function cancha(){
        return $this->belongsTo(Cancha::class, 'cancha_id');
    }

    public function usuario(){
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function cancelaciones(){
        return $this->hasMany(TurnoCancelacion::class);
    }
}
