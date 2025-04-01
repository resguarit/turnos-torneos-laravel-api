<?php
// app/Models/Grupo.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Grupo extends Model
{
    use HasFactory;

    protected $fillable = ['nombre', 'zona_id'];

    public function zona()
    {
        return $this->belongsTo(Zona::class);
    }

    public function equipos()
    {
        return $this->belongsToMany(Equipo::class, 'equipo_grupo');
    }
}