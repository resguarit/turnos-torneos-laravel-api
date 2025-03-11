<?php

namespace App\Services\Implementation;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use App\Services\Interface\AuthServiceInterface;
use App\Services\Interface\AuditoriaServiceInterface;

class AuthService implements AuthServiceInterface
{
    protected $auditoriaService;
    
    public function __construct(AuditoriaServiceInterface $auditoriaService)
    {
        $this->auditoriaService = $auditoriaService;
    }
    
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
        
        $this->auditoriaService->registrar(
            'login', 
            'users', 
            $user->id, 
            null,
            ['ip' => request()->ip(), 'user_agent' => request()->userAgent()],
            $user->id
        );

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

        $this->auditoriaService->registrar(
            'register',
            'users',
            $user->id,
            null,
            ['ip' => request()->ip(), 'user_agent' => request()->userAgent()],
            $user->id
        );

        return [
            'message' => 'Usuario registrado exitosamente',
            'user' => $user,
            'status' => 201
        ];
    }

    public function logout($user)
    {
        $this->auditoriaService->registrar(
            'logout',
            'users',
            $user->id,
            null,
            ['ip' => request()->ip(), 'user_agent' => request()->userAgent()],
            $user->id
        );
        
        $user->currentAccessToken()->delete();
        
        return [
            'message' => 'Sesión cerrada con éxito',
            'status' => 200
        ];
    }
}