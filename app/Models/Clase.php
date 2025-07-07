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
        'fecha',
        'profesor_id',
        'cancha_id',
        'horario_id',
        'cupo_maximo',
        'precio_mensual',
        'activa',
        'tipo'
    ];

    public function profesor()
    {
        return $this->belongsTo(Profesor::class, 'profesor_id');
    }

    public function cancha()
    {
        return $this->belongsTo(Cancha::class, 'cancha_id');
    }

    public function horario()
    {
        return $this->belongsTo(Horario::class, 'horario_id');
    }
}