<?php

namespace App\Services\Implementation;

use App\Models\User;
use App\Models\Auditoria;
use App\Models\Persona;
use Illuminate\Support\Facades\Hash;
use App\Services\Interface\AuthServiceInterface;
use Illuminate\Support\Facades\DB;
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
            $user = User::with('persona')->where('dni', $credentials['dni'])->first();
        } else {
            $user = User::with('persona')->where('email', $credentials['email'])->first();
        }

        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            return [
                'message' => 'Credenciales incorrectas',
                'status' => 401
            ];
        }

        $abilities = $user->getAbilities();
        $token = $user->createToken('login', $abilities);

        return [
            'token' => $token->plainTextToken,
            'user_id' => $user->id,
            'rol' => $user->rol,
            'username' => $user->persona->name ?? 'Usuario', // Asegurándonos de tener un valor predeterminado si es null
            'dni' => $user->dni,
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