<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TipoGasto extends Model
{
    use HasFactory;

    protected $table = 'tipos_gasto';

    protected $fillable = [
        'nombre'
    ];

    /**
     * Obtener las transacciones asociadas a este tipo de gasto.
     */
    public function transacciones()
    {
        return $this->hasMany(Transaccion::class, 'tipo_gasto_id');
    }
} 