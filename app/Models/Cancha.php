<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cancha extends Model
{
    //
    use HasFactory;

    protected $table = 'canchas';

    protected $fillable = ['nro', 'tipo_cancha', 'precio_por_hora', 'seña', 'activa'];

    protected $hidden = ['created_at', 'updated_at'];

    public function horario(){
        return $this->belongsTo(Horario::class, 'horario_id');
    }

    public function cancha(){
        return $this->belongsTo(Cancha::class, 'cancha_id');
    }

}
