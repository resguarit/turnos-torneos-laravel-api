<?php

namespace App\Services\Implementation;

use App\Models\User;
use App\Models\Auditoria;
use Illuminate\Support\Facades\Hash;
use App\Services\Interface\AuthServiceInterface;

class AuthService implements AuthServiceInterface
{
    public function login(array $credentials)
    {
        if (isset($credentials['dni'])) {
            $user = User::where('dni', $credentials['dni'])->first();
        } else {
            $user = User::where('email', $credentials['email'])->first();
        }

        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            return [
                'message' => 'Credenciales incorrectas',
                'status' => 401
            ];
        }

        $abilities = $user->getAbilities();
        $token = $user->createToken('login', $abilities);

        // Registrar en auditorías
        Auditoria::create([
            'usuario_id' => $user->id,
            'accion' => 'login',
            'entidad' => 'User',
            'entidad_id' => $user->id,
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'fecha_accion' => now()
        ]);

        return [
            'token' => $token->plainTextToken,
            'user_id' => $user->id,
            'rol' => $user->rol,
            'username' => $user->name,
            'status' => 200
        ];
    }

    public function register(array $data)
    {
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'dni' => $data['dni'],
            'telefono' => $data['telefono'],
            'password' => Hash::make($data['password']),
            'rol' => 'cliente'
        ]);

        return [
            'message' => 'Usuario registrado exitosamente',
            'user' => $user,
            'status' => 201
        ];
    }

    public function logout($user)
    {
        $user->currentAccessToken()->delete();
        
        return [
            'message' => 'Sesión cerrada con éxito',
            'status' => 200
        ];
    }
}