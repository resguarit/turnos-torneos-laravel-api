<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Descuento extends Model
{
    use HasFactory;

    protected $table = 'descuentos';

    protected $fillable = [
        'motivo',
        'tipo',
        'valor',
        'fecha',
        'cancha_id',
        'horario_id',
    ];

    protected $casts = [
        'fecha' => 'date'
    ];
    
    public function cancha()
    {
        return $this->belongsTo(Cancha::class);
    }

    public function horario()
    {
        return $this->belongsTo(Horario::class);
    }
}
