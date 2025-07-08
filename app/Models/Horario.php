<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Descuento;

class Horario extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'horarios';

    protected $fillable = [
        'hora_inicio', 
        'hora_fin', 
        'activo',
        'dia',
        'deporte_id'
    ];

    protected $hidden = ['created_at', 'updated_at'];

    public function deporte()
    {
        return $this->belongsTo(Deporte::class);
    }

    public function descuentos()
    {
        return $this->hasMany(Descuento::class, 'horario_id');
    }
}
