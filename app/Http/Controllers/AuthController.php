<?php
namespace App\Http\Controllers;

use Laravel\Socialite\Facades\Socialite;
use Illuminate\Http\Request;
use App\Models\User;

class AuthController extends Controller
{
    public function handleGoogleCallback(Request $request)
    {
        $googleToken = $request->input('token');

        if (!$googleToken) {
            return response()->json(['message' => 'Token de Google no proporcionado'], 400);
        }

        // Validar el token con Google
        $client = new \Google\Client(['client_id' => env('GOOGLE_CLIENT_ID')]);
        $payload = $client->verifyIdToken($googleToken);

        if (!$payload) {
            return response()->json(['message' => 'Token de Google no válido'], 401);
        }

        // Extraer información del usuario
        $googleId = $payload['sub'];
        $email = $payload['email'];
        $name = $payload['name'];
        $phone = $payload['phone_number'] ?? null; // Asegúrate de manejar valores nulos

        // Buscar o crear al usuario
        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'google_id' => $googleId,
                'telefono' => $phone,
                'password' => bcrypt(str()->random(24)),
                'rol' => 'cliente',
            ]
        );

        // Generar un token de sesión
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
        ]);
    }
}
