<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TurnoModificacion extends Model
{
    use HasFactory;

    protected $table = 'turno_modificaciones';

    protected $fillable = [
        'turno_id',
        'modificado_por',
        'datos_anteriores',
        'datos_nuevos',
        'motivo',
        'fecha_modificacion'
    ];

    protected $casts = [
        'fecha_modificacion' => 'datetime',
        'datos_anteriores' => 'array',
        'datos_nuevos' => 'array'
    ];

    public function turno(){
        return $this->belongsTo(Turno::class);
    }

    public function usuario(){
        return $this->belongsTo(User::class, 'modificado_por');
    }
}
