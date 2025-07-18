<?php

namespace App\Models;

use App\Enums\TurnoEstado;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Descuento;

class Turno extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'turnos';

    protected $fillable = ['fecha_turno', 'fecha_reserva', 'horario_id', 'cancha_id', 'persona_id', 'monto_total', 'monto_seña', 'descuento_id', 'estado', 'tipo', 'partido_id'];

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

    public function persona()
    {
        return $this->belongsTo(Persona::class);
    }

    public function horario(){
        return $this->belongsTo(Horario::class, 'horario_id');
    }

    public function cancha(){
        return $this->belongsTo(Cancha::class, 'cancha_id');
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

    public function partido()
    {
        return $this->belongsTo(\App\Models\Partido::class, 'partido_id');
    }

    public function descuento()
    {
        return $this->belongsTo(Descuento::class, 'descuento_id');
    }
}
