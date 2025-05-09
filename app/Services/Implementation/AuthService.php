<?php

namespace App\Services\Implementation;

use App\Models\User;
use App\Models\Persona;
use App\Models\Auditoria;
use Illuminate\Support\Facades\Hash;
use App\Services\Interface\AuthServiceInterface;
use Illuminate\Support\Facades\DB;
use App\Models\CuentaCorriente;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Notifications\ConfirmEmailNotification;
class AuthService implements AuthServiceInterface
{
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
            'username' => $user->persona->name ?? 'Usuario', // Asegurándonos de tener un valor predeterminado si es null
            'status' => 200
        ];
    }

    public function register(array $data)
    {
        DB::beginTransaction();

        // Limpiar el DNI de puntos y espacios
        $data['dni'] = str_replace(['.', ' '], '', $data['dni']);
        
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

            $token = VerifyEmailController::generateVerificationToken($data['email']);
            $confirmationLink = 'http://localhost:5173/verify-email?email=' . $data['email'] . '&token=' . $token;
            $user->notify(new ConfirmEmailNotification($user, $confirmationLink));
            
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