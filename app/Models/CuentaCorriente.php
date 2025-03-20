<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CuentaCorriente extends Model
{
    use SoftDeletes;

    // Especificar el nombre correcto de la tabla
    protected $table = 'cuentas_corrientes';

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
