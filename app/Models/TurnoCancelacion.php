<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TurnoCancelacion extends Model
{
    use HasFactory;

    protected $table = 'turno_cancelaciones';

    protected $fillable = [
        'turno_id',
        'cancelado_por',
        'motivo',
        'fecha_cancelacion'
    ];

    protected $casts = [
        'fecha_cancelacion' => 'datetime'
    ];

    public function turno(){
        return $this->belongsTo(Turno::class);
    }

    public function usuario(){
        return $this->belongsTo(User::class, 'caneclado_por');
    }
}
