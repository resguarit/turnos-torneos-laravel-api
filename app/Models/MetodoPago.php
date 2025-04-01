<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class MetodoPago extends Model
{
    use HasFactory;
    
    protected $table = 'metodos_pago';

    protected $fillable = ['nombre', 'descripcion', 'activo'];  

    public function transacciones()
    {
        return $this->hasMany(Transaccion::class);
    }
}
