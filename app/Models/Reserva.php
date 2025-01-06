<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Reserva extends Model
{
    use HasFactory;

    protected $table = 'reservas';

    protected $fillable = ['fecha_turno', 'fecha_reserva', 'horarioCanchaID', 'usuarioID', 'monto_total', 'monto_seña', 'estado', 'tipo'];

    protected $hidden = ['created_at', 'updated_at'];

    
    protected $dates = ['fecha_turno', 'fecha_reserva'];

    public function getFechaTurnoAttribute($value)
    {
        return Carbon::parse($value);
    }

    public function horarioCancha(){
        return $this->belongsTo(HorarioCancha::class, 'horarioCanchaID');
    }

    public function usuario(){
        return $this->belongsTo(User::class, 'usuarioID');
    }
}
