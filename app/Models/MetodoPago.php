<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
class MetodoPago extends Model
{
    use HasFactory, SoftDeletes;
    
    protected $table = 'metodos_pago';

    protected $fillable = ['nombre', 'descripcion', 'activo'];  

    public function transacciones()
    {
        return $this->hasMany(Transaccion::class);
    }
}
