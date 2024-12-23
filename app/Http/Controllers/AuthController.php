<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        
    }

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string',
            'password' => 'required|string'
        ]);

        if ($validator->fails()) {
            $data = [
                'message' => 'Error de validación',
                'errors' => $validator->errors(),
                'status' => 422
            ];

            return response()->json($data, 422);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            $data = [
                'message' => 'Credenciales incorrectas',
                'status' => 401
            ];

            return response()->json($data, 401);
        }

        $token = $user->createToken('myapptoken')->plainTextToken;

        $data = [
            'message' => 'Usuario logueado',
            'user' => $user,
            'token' => $token,
            'status' => 200
        ];

        return response()->json($data, 200);
    }

}
