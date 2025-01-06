<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BloqueoTemporal extends Model
{
    use HasFactory;

    protected $table = 'bloqueo_temporal';

    protected $fillable = [
        'usuario_id',
        'horario_cancha_id',
        'fecha',
        'expira_en',
    ];

    public $timestamps = true;   
}

