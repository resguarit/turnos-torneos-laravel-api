<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;
    use HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'email',
        'telefono',
        'password',
        'rol',
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
            'reserva:bloqueo',
            'horariosNoDisponible:show',
            'horarios:show',
            'horarios:showOne',
            'reservas:create',
            'reservas:update',
            'reservas:destroy',
            'usuario:update',
            "horarios:fecha",
        ],
        'moderador' => [
            'canchas:show',
            'canchas:update',
            'reserva:bloqueo',
            'horariosNoDisponible:show',
            'horarios:show',
            'horarios:create',
            'horarios:showOne',
            'reservas:show',
            'reservas:create',
            'reservas:update',
            'reservas:destroy',
            'usuario:update',
            "horarios:indisponibilizar",
            "horarios:fecha",
        ],
        'admin' => ['*'],
    ];

    public function getAbilities()
    {
        return self::ROLES[$this->rol] ?? [];
    }

}
