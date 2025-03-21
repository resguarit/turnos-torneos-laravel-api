<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Persona;
use App\Models\Transaccion;

class Caja extends Model
{
    protected $fillable = [
        'empleado_id',
        'fecha_apertura',
        'fecha_cierre',
        'saldo_inicial',
        'saldo_final',
        'activa',
        'observaciones'
    ];

    public function empleado()
    {
        return $this->belongsTo(Persona::class);
    }

    public function transacciones()
    {
        return $this->hasMany(Transaccion::class);
    }

}
