<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transaccion extends Model
{
    use SoftDeletes;

    protected $table = 'transacciones';

    protected $fillable = [
        'cuenta_corriente_id', 
        'turno_id', 
        'monto', 
        'tipo', 
        'descripcion', 
        'caja_id', 
        'metodo_pago_id', 
        'payment_id', 
        'torneo_id',
        'tipo_gasto_id',
        'evento_id',
        'metodo_pago_id'
    ];
    
    /**
     * Relación con la tabla `cuentas_corrientes`.
     */
    public function cuentaCorriente()
    {
        return $this->belongsTo(CuentaCorriente::class);
    }

    public function turno()
    {
        return $this->belongsTo(Turno::class);
    }

    public function caja()
    {
        return $this->belongsTo(Caja::class);
    }

    public function metodoPago()
    {
        return $this->belongsTo(MetodoPago::class);
    }

    public function evento()
    {
        return $this->belongsTo(Evento::class);
    }
    
    /**
     * Obtener el tipo de gasto asociado a esta transacción.
     */
    public function tipoGasto()
    {
        return $this->belongsTo(TipoGasto::class, 'tipo_gasto_id');
    }
}
