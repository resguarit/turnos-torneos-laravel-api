<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Persona;
use App\Models\Transaccion;

class Caja extends Model
{
    protected $fillable = [
        'fecha_apertura',
        'fecha_cierre',
        'saldo_inicial',
        'saldo_final',
        'empleado_id',
        'activa',
        'observaciones'
    ];

    protected $casts = [
        'fecha_apertura' => 'datetime',
        'fecha_cierre' => 'datetime',
        'activa' => 'boolean'
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
