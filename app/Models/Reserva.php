<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Reserva extends Model
{
    use HasFactory;

    protected $table = 'reservas';

    protected $fillable = ['fecha_turno', 'fecha_reserva', 'horarioCanchaID', 'usuarioID', 'monto_total', 'monto_seña', 'estado'];

    protected $hidden = ['created_at', 'updated_at'];

    
    public function horarioCancha(){
        return $this->belongsTo(HorarioCancha::class, 'horarioCanchaID');
    }

    public function usuario(){
        return $this->belongsTo(User::class, 'usuarioID');
    }
}
