<?php

namespace App\Services\Implementation;

use App\Models\User;
use App\Models\Persona;
use App\Models\Auditoria;
use Illuminate\Support\Facades\Hash;
use App\Services\Interface\AuthServiceInterface;
use Illuminate\Support\Facades\DB;
use App\Services\Interface\AuditoriaServiceInterface;
use App\Models\CuentaCorriente;

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
        DB::beginTransaction();
        
        try {
            // Buscar si ya existe una persona con el mismo DNI
            $persona = Persona::where('dni', $data['dni'])->first();

            if (!$persona) {
                // Si no existe, crear una nueva persona
                $persona = Persona::create([
                    'name' => $data['name'],
                    'dni' => $data['dni'],
                    'telefono' => $data['telefono'],
                    'direccion' => $data['direccion'] ?? null,
                ]);
            }

            if (!$persona->cuentaCorriente) {
                $cuentaCorriente = CuentaCorriente::create([
                    'persona_id' => $persona->id,
                    'saldo' => 0
                ]);
            }

            if ($persona->user) {
                return [
                    'message' => 'Ya existe un usuario registrado con este DNI',
                    'status' => 400
                ];
            }

            // Crear usuario asociado a la persona
            $user = User::create([
                'email' => $data['email'],
                'dni' => $data['dni'],
                'password' => Hash::make($data['password']),
                'rol' => 'cliente',
                'persona_id' => $persona->id
            ]);
            
            DB::commit();
            
            return [
                'message' => 'Usuario registrado exitosamente',
                'user' => $user,
                'persona' => $persona,
                'status' => 201
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            
            return [
                'message' => 'Error al registrar usuario: ' . $e->getMessage(),
                'status' => 500
            ];
        }
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