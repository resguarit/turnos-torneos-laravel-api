<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\CuentaCorriente;

class Persona extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'dni', 'direccion', 'telefono'];

    public function usuario()
    {
        return $this->hasOne(User::class);
    }

    public function turnos()
    {
        return $this->hasMany(Turno::class);
    }

    public function cuentaCorriente()
    {
        return $this->hasOne(CuentaCorriente::class);
    }
}
