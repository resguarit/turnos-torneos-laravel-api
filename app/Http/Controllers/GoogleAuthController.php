<?php
namespace App\Http\Controllers;

use Laravel\Socialite\Facades\Socialite;
use Illuminate\Http\Request;
use App\Models\User;

class GoogleAuthController extends Controller
{
    public function redirectToGoogle()
    {
        return Socialite::driver('google')
            ->stateless()
            ->redirect();
    }

    public function handleGoogleCallback(Request $request)
    {
        $googleToken = $request->input('token');

        if (!$googleToken) {
            return response()->json(['message' => 'Token de Google no proporcionado'], 400);
        }

        // Configure client to not verify SSL certificates in development
        $client = new \Google\Client([
            'client_id' => env('GOOGLE_CLIENT_ID'),
            'verify' => false // Disable SSL verification
        ]);

        try {
            $payload = $client->verifyIdToken($googleToken);

            if (!$payload) {
                return response()->json(['message' => 'Token de Google no válido'], 401);
            }

            // Extract user info
            $googleId = $payload['sub'];
            $email = $payload['email'];
            $name = $payload['name'];
            $phone = $payload['phone_number'] ?? null;

            // Create or update user
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

            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'user' => $user,
                'token' => $token
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al verificar el token de Google',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}