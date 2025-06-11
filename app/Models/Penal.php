<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Penal extends Model
{
    use HasFactory;

    protected $table = 'penales';

    protected $fillable = ['partido_id', 'equipo_local_id', 'equipo_visitante_id', 'penales_local', 'penales_visitante'];

    public function equipoLocal()
    {
        return $this->belongsTo(Equipo::class, 'equipo_local_id');
    }

    public function equipoVisitante()
    {
        return $this->belongsTo(Equipo::class, 'equipo_visitante_id');
    }

    public function partido()
    {
        return $this->belongsTo(Partido::class, 'partido_id');
    }
}