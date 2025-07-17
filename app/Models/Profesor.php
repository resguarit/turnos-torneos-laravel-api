<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Profesor extends Model
{
    protected $table = 'profesores';

    protected $fillable = [
        'nombre',
        'apellido',
        'dni',
        'telefono',
        'email',
        'especialidad'
    ];

    public function clases()
    {
        return $this->hasMany(Clase::class, 'profesor_id');
    }
}