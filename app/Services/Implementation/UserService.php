<?php

namespace App\Services\Implementation;

use App\Services\Interface\UserServiceInterface;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Persona;
use App\Services\Implementation\AuditoriaService;

class UserService implements UserServiceInterface
{
    public function register(array $data)
    {
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

        $user = User::create([
            'email' => $data['email'],
            'dni' => $data['dni'],
            'password' => Hash::make($data['password']),
            'rol' => 'cliente',
            'persona_id' => $persona->id
        ]);

        return [
            'message' => 'Usuario cliente creado con éxito',
            'status' => 201
        ];
    }

    public function createUser(Request $request)
    {
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'dni' => $request->dni,
            'telefono' => $request->telefono,
            'password' => Hash::make($request->password),
            'rol' => $request->rol ?? 'cliente'
        ]);

        AuditoriaService::registrar(
            'crear', 
            'usuarios', 
            $user->id, 
            null, 
            json_encode($user->toArray(), JSON_PRETTY_PRINT)
        );

        return [
            'message' => 'Usuario creado correctamente',
            'user' => $user,
            'status' => 201
        ];
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
            'username' => $user->persona->name,
        ];
    }

    public function getUsers($request)
    {
        $perPage = $request->query('limit', 10);
        $sortBy = $request->query('sortBy', 'created_at');
        $order = $request->query('order', 'desc');
        $page = $request->query('page', 1);
        $searchType = $request->query('searchType');
        $searchTerm = $request->query('searchTerm');

        $query = User::with('persona')->orderBy($sortBy, $order);

        if ($searchType && $searchTerm) {
            if ($searchType === 'name' || $searchType === 'dni' || $searchType === 'telefono') {
                $query->whereHas('persona', function ($q) use ($searchType, $searchTerm) {
                    $q->where($searchType, 'like', "%{$searchTerm}%");
                });
            } else {
                $query->where($searchType, 'like', "%{$searchTerm}%");
            }
        }

        $users = $query->paginate($perPage, ['*'], 'page', $page);

        return [
            'usuarios' => $users->items(),
            'status' => 200,
            'totalUsuarios' => $users->total(),
            'totalPages' => $users->lastPage(),
            'currentPage' => $users->currentPage(),
            'perPage' => $users->perPage(),
        ];
    }

    public function show($id)
    {
        $user = User::with('persona')->find($id);

        if (!$user) {
            return [
                'message' => 'Usuario no encontrado',
                'status' => 404
            ];
        }

        return [
            'user' => $user,
            'status' => 200
        ];
    }

    public function updateUser(Request $request, $id)
    {
        $user = User::findOrFail($id);
        $datosAnteriores = $user->toArray();
        
        $user->update($request->all());

        AuditoriaService::registrar(
            'modificar', 
            'usuarios', 
            $user->id, 
            $datosAnteriores, 
            $user->fresh()->toArray()
        );

        return response()->json([
            'message' => 'Usuario actualizado correctamente',
            'user' => $user,
            'status' => 200
        ], 200);
    }

    public function deleteUser($id)
    {
        $user = User::findOrFail($id);
        $datosAnteriores = $user->toArray();
        
        $user->delete();

        AuditoriaService::registrar(
            'eliminar', 
            'usuarios', 
            $id, 
            $datosAnteriores, 
            null
        );

        return response()->json([
            'message' => 'Usuario eliminado correctamente',
            'status' => 200
        ], 200);
    }

    public function update($id, array $data)
    {
        $user = User::with('persona')->find($id);

        if (!$user) {
            return [
                'message' => 'Usuario no encontrado',
                'status' => 404
            ];
        }

        // Manejar contraseña
        if (isset($data['password'])) {
            if (!Hash::check($data['current_password'], $user->password)) {
                return [
                    'message' => 'La contraseña actual no es correcta',
                    'status' => 401
                ];
            }
            $user->password = Hash::make($data['password']);
        }

        // Actualizar campos específicos de User
        if (isset($data['email'])) {
            $user->email = $data['email'];
        }
        if (isset($data['rol'])) {
            $user->rol = $data['rol'];
        }
        if (isset($data['dni'])) {
            $user->dni = $data['dni'];
        }
        
        $user->save();

        // Actualizar campos de Persona si existe
        if ($user->persona) {
            if (isset($data['name'])) {
                $user->persona->name = $data['name'];
            }
            if (isset($data['dni'])) {
                $user->persona->dni = $data['dni'];
            }
            if (isset($data['telefono'])) {
                $user->persona->telefono = $data['telefono'];
            }
            if (isset($data['direccion'])) {
                $user->persona->direccion = $data['direccion'];
            }
            
            $user->persona->save();
        }

        return [
            'message' => 'Usuario actualizado correctamente',
            'status' => 200
        ];
    }

    public function destroy($id)
    {
        $user = User::find($id);
        
        if (!$user) {
            return [
                'message' => 'Usuario no encontrado',
                'status' => 404
            ];
        }
        
        $datosAnteriores = json_encode($user->toArray(), JSON_PRETTY_PRINT);
        
        $user->delete();

        AuditoriaService::registrar(
            'eliminar', 
            'usuarios', 
            $id, 
            $datosAnteriores, 
            null
        );

        return [
            'message' => 'Usuario eliminado con éxito',
            'status' => 200
        ];
    }

    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'limit' => 'integer|min:1',
            'sortBy' => 'string|in:name,email,created_at,dni,telefono',
            'order' => 'string|in:asc,desc',
            'page' => 'integer|min:1',
            'searchType' => 'string|nullable|in:name,email,dni,telefono',
            'searchTerm' => 'string|nullable',
        ]);

        if ($validator->fails()) {
            return [
                'message' => 'Error en la validación',
                'errors' => $validator->errors(),
                'status' => 422
            ];
        }

        $perPage = $request->query('limit', 10);
        $sortBy = $request->query('sortBy', 'created_at');
        $order = $request->query('order', 'desc');
        $page = $request->query('page', 1);
        $searchType = $request->query('searchType');
        $searchTerm = $request->query('searchTerm');

        $query = User::with('persona')->orderBy($sortBy, $order);

        if ($searchType && $searchTerm) {
            if ($searchType === 'name' || $searchType === 'dni' || $searchType === 'telefono') {
                $query->whereHas('persona', function ($q) use ($searchType, $searchTerm) {
                    $q->where($searchType, 'like', "%{$searchTerm}%");
                });
            } else {
                $query->where($searchType, 'like', "%{$searchTerm}%");
            }
        }

        $users = $query->paginate($perPage, ['*'], 'page', $page);

        return [
            'usuarios' => $users->items(),
            'status' => 200,
            'totalUsuarios' => $users->total(),
            'totalPages' => $users->lastPage(),
            'currentPage' => $users->currentPage(),
            'perPage' => $users->perPage(),
        ];
    }
}