<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Complejo extends Model
{
    protected $connection = 'mysql_central';

    protected $fillable = [
        'nombre',
        'subdominio',
        'db_host',
        'db_port',
        'db_database',
        'db_username',
        'db_password',
    ];

    protected $casts = [
        'db_password' => 'encrypted',
    ];
}
