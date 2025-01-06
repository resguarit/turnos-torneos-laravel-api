<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string',
            'email' => 'required|string|email|unique:users',
            'telefono' => 'required|string|max:15',
            'password' => 'required|string|min:8|confirmed',
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
        'password' => 'required|string|min:8|confirmed',
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
                'user_id' => $user -> id,
                'rol' => $user -> rol,
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

    public function index(){
        
        $user = Auth::user();

        abort_unless($user->tokenCan('usuario:show') || $user->rol === 'admin', 403, 'No tienes permisos para realizar esta acción');

        $users = User::all();

        $data = [
            'usuarios' => $users,
            'status' => 200
        ];

        return response()->json($data,200);
    }
    
    public function show ($id){
        $user = Auth::user();

        abort_unless($user->tokenCan('usuario:showOne') || $user->rol === 'admin', 403, 'No tienes permisos para realizar esta acción');

        $validator = Validator::make(['id' => $id], [
            'id' => 'required|integer|exists:users,id'
        ]);

        if($validator->fails()){
            return response()->json([
                'message' => 'Error en la validación',
                'errors' => $validator->errors(),
                'status' => 400
            ], 400);
        }

        $userToShow = User::find($id);

        if (!$userToShow) {
            return response()->json([
                'message' => 'Usuario no encontrado',
                'status' => 404
            ], 404);
        }

        return response()->json([
            'user' => $userToShow,
            'status' => 200
        ], 200);
    }

    public function update(Request $request, $id)
{
    $user = Auth::user();

    abort_unless($user->tokenCan('usuario:update') || $user->rol === 'admin', 403, 'No tienes permisos para realizar esta acción');

    $userToUpdate = User::find($id);

    if (!$userToUpdate) {
        return response()->json([
            'message' => 'Usuario no encontrado',
            'status' => 404
        ], 404);
    }

    // Validar los datos de entrada
    $validator = Validator::make($request->all(), [
        'name' => 'sometimes|string|unique:users,name,' . $id,
        'email' => 'sometimes|string|email|unique:users,email,' . $id,
        'telefono' => 'sometimes|string|max:15',
        'password' => 'sometimes|string|min:8|confirmed',
        'current_password' => 'sometimes|required_with:password|string',
    ]);

    // Manejar errores de validación
    if ($validator->fails()) {
        return response()->json([
            'message' => 'Error en la validación',
            'errors' => $validator->errors(),
            'status' => 400
        ], 400);
    }

    // Verificar la contraseña actual si se está cambiando la contraseña
    if ($request->has('password') && !Hash::check($request->current_password, $userToUpdate->password)) {
        return response()->json([
            'message' => 'La contraseña actual no es correcta',
            'status' => 401
        ], 401);
    }

    // Actualizar los campos del usuario
    if ($request->has('name')) {
        $userToUpdate->name = $request->name;
    }

    if ($request->has('email')) {
        $userToUpdate->email = $request->email;
    }

    if ($request->has('telefono')) {
        $userToUpdate->telefono = $request->telefono;
    }

    if ($request->has('password')) {
        $userToUpdate->password = Hash::make($request->password);
    }

    // Guardar los cambios en la base de datos
    $userToUpdate->save();

    // Respuesta exitosa
    return response()->json([
        'message' => 'Usuario actualizado correctamente',
        'status' => 200
    ], 200);

    }

}


