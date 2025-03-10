<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CuentaCorriente extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'persona_id', 'saldo'
    ];

    /**
     * Relación con la tabla `personas`.
     */
    public function persona()
    {
        return $this->belongsTo(Persona::class);
    }

    /**
     * Relación con la tabla `transacciones`.
     */
    public function transacciones()
    {
        return $this->hasMany(Transaccion::class);
    }
}
