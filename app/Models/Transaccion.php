<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transaccion extends Model
{
    use SoftDeletes;

    protected $table = 'transacciones';

    protected $fillable = [
        'cuenta_corriente_id', 'monto', 'tipo', 'descripcion'
    ];

    /**
     * Relación con la tabla `cuentas_corrientes`.
     */
    public function cuentaCorriente()
    {
        return $this->belongsTo(CuentaCorriente::class);
    }
}
