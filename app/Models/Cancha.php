<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Cancha extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'canchas';

    protected $fillable = ['nro', 'tipo_cancha', 'precio_por_hora', 'seña', 'activa', 'descripcion', 'deporte_id'];

    protected $hidden = ['created_at', 'updated_at'];

    public function horario(){
        return $this->belongsTo(Horario::class, 'horario_id');
    }

    public function cancha(){
        return $this->belongsTo(Cancha::class, 'cancha_id');
    }

    public function turnos()
    {
        return $this->hasMany(Turno::class, 'cancha_id');
    }

    public function bloqueosTemporales()
    {
        return $this->hasMany(BloqueoTemporal::class, 'cancha_id');
    }

    public function deporte()
    {
        return $this->belongsTo(Deporte::class);
    }
}
