<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Persona;


class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;
    use HasApiTokens;
    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'email',
        'dni',
        'password',
        'rol',
        'google_id',
        'persona_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
    const ROLES = [
        'cliente' => [
            'canchas:show',
            'turnos:bloqueo',
            'horariosNoDisponible:show',
            'turnos:ownShow',
            'turnos:show',
            'horarios:show',
            'horarios:showOne',
            'turnos:create',
            'turnos:update',
            'turnos:destroy',
            'usuario:showOne',
            'usuario:update',
            "usuario:showOne",
            "horarios:fecha",
            "disponibilidad:canchas",
            "turnos:cancelarBloqueo",
        ],
        'moderador' => [
            'canchas:show',
            "cancha:showOne",
            'canchas:update',
            'turnos:bloqueo',
            'horariosNoDisponible:show',
            'horarios:show',
            'horarios:create',
            'horarios:showOne',
            'turnos:show',
            'turnos:create',
            'turnos:update',
            "turnos:show_all",
            'turnos:destroy',
            "turnos:createTurnoFijo",
            "usuario:show",
            'usuario:showOne',
            'usuario:update',
            "usuario:showOne",
            "horarios:indisponibilizar",
            "horarios:fecha",
            "disponibilidad:canchas",
            "turnos:cancelarBloqueo",
            'horarios:update',
            'franjasNoDisponible:show',
        ],
        'admin' => ['*'],
    ];

    public function getAbilities()
    {
        return self::ROLES[$this->rol] ?? [];
    }

    public function turnos(){
        return $this->hasMany(Turno::class, 'usuario_id');
    }

    public function persona()
    {
        return $this->belongsTo(Persona::class);
    }

}
