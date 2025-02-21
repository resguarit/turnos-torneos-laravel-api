<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class Horario extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'horarios';

    protected $fillable = [
        'hora_inicio', 
        'hora_fin', 
        'activo',
        'dia'
    ];

    protected $hidden = ['created_at', 'updated_at'];

}
