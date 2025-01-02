<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string',
            'email' => 'required|string|email|unique:users',
            'telefono' => 'required|string|max:15',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            $data = [
                'message' => 'Error de validación',
                'errors' => $validator->errors(),
                'status' => 422
            ];

            return response()->json($data, 422);
        }

        $user = new User([
            'name' => $request->name,
            'email' => $request->email,
            'telefono' => $request->telefono,
            'password' => Hash::make($request->password),
            'rol' => 'cliente' 
        ]);

        $user->save();

        return response()->json([
            'message' => 'Usuario cliente creado con éxito',
            'status' => 201
        ], 201);
    }

    public function createUser(Request $request)
    {
        $authUser = Auth::user();

        abort_unless($authUser->rol === 'admin', 403, 'No tienes permisos para realizar esta acción');

        $validator = Validator::make($request->all(), [
            'name' => 'required|string',
            'email' => 'required|string|email|unique:users',
            'telefono' => 'required|string|max:15',
            'password' => 'required|string',
            'rol' => 'required|string|in:cliente,moderador,admin'
        ]);

        if ($validator->fails()) {
            $data = [
                'message' => 'Error de validación',
                'errors' => $validator->errors(),
                'status' => 422
            ];

            return response()->json($data, 422);
        }

        $user = new User([
            'name' => $request->name,
            'email' => $request->email,
            'telefono' => $request->telefono,
            'password' => Hash::make($request->password),
            'rol' => $request->rol
        ]);

        $user->save();

        return response()->json([
            'message' => 'Usuario creado con éxito',
            'status' => 201
        ], 201);
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

        if (!$user) {
            return response()->json([
                'message' => 'Usuario no encontrado',
                'status' => 404
            ], 404);
        }

        if ($user && Hash::check($request->password, $user->password)) {
            $abilities = $this->resolveAbilities($user);
            $token = $user->createToken('login', $abilities);
            return [
                'token' => $token->plainTextToken,
            ];
        }

        return response()->json([
            'message' => 'Credenciales incorrectas',
            'status' => 401
        ], 401);
    }

    protected function resolveAbilities(User $user)
    {
        return $user->getAbilities();
    }
}
