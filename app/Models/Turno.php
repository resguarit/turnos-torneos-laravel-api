<?php

namespace App\Models;

use App\Enums\TurnoEstado;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\SoftDeletes;

class Turno extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'turnos';

    protected $fillable = ['fecha_turno', 'fecha_reserva', 'horario_id', 'cancha_id', 'usuario_id', 'monto_total', 'monto_seña', 'estado', 'tipo'];

    protected $hidden = ['created_at', 'updated_at'];

    protected $dates = ['fecha_turno', 'fecha_reserva', 'deleted_at'];

    protected $casts = [
        'estado' => TurnoEstado::class,
        'fecha_turno' => 'datetime',
        'fecha_reserva' => 'datetime'
    ];

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

    public function modificaciones(){
        return $this->hasMany(TurnoModificacion::class);
    }

    public function motivo_cancelacion(){
        return $this->hasOne(TurnoCancelacion::class, 'turno_id')->latest();
    }
}
