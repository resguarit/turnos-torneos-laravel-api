<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;

class Configuracion extends Model
{

    protected $table = 'configuraciones';
    
    protected $fillable = [
        'colores',
        'habilitar_turnos',
        'habilitar_mercado_pago',
        'mercado_pago_public_key',
        'mercado_pago_access_token',
        'mercado_pago_webhook_secret',
        'nombre_complejo',
        'logo_complejo',
        'direccion_complejo',
        'telefono_complejo',
    ];

    protected $casts = [
        'colores' => 'array',
        'habilitar_turnos' => 'boolean',
        'habilitar_mercado_pago' => 'boolean',
        'mercado_pago_access_token' => 'encrypted',
        'mercado_pago_webhook_secret' => 'encrypted',
    ];

}
