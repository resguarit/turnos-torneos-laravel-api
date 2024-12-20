<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cancha extends Model
{
    //
    use HasFactory;

    protected $table = 'canchas';

    protected $fillable = ['nro', 'tipoCancha', 'precioPorHora', 'activa'];

}
