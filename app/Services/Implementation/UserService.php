<?php

namespace App\Services\Implementation;

use App\Services\Interface\UserServiceInterface;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Persona;
use App\Services\Implementation\AuditoriaService;
use Illuminate\Support\Facades\DB;
use App\Models\CuentaCorriente;

class UserService implements UserServiceInterface
{
    public function register(array $data)
    {
        // Buscar si ya existe una persona con el mismo DNI
        $persona = Persona::where('dni', $data['dni'])->first();

        

        if (!$persona) {
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
        try {
            DB::beginTransaction();

            // Crear la persona
            $persona = Persona::create([
                'name' => $request->name,
                'dni' => $request->dni,
                'telefono' => $request->telefono,
                'direccion' => $request->direccion ?? null,
            ]);

            // Crear la cuenta corriente para la persona
            $cuentaCorriente = CuentaCorriente::create([
                'persona_id' => $persona->id,
                'saldo' => 0
            ]);

            // Crear el usuario
            $user = User::create([
                'email' => $request->email,
                'dni' => $request->dni,
                'password' => Hash::make($request->password),
                'rol' => $request->rol ?? 'cliente',
                'persona_id' => $persona->id
            ]);

            AuditoriaService::registrar(
                'crear', 
                'usuarios', 
                $user->id, 
                null, 
                $user->toArray()
            );

            DB::commit();

            return [
                'message' => 'Usuario creado correctamente',
                'user' => $user,
                'persona' => $persona,
                'status' => 201
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            return [
                'message' => 'Error al crear el usuario: ' . $e->getMessage(),
                'status' => 500
            ];
        }
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
            'dni' => $user->dni,
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

    public function destroy($id)
    {
        try {
            DB::beginTransaction();

            $user = User::with(['persona'])->find($id);
            
            if (!$user) {
                return [
                    'message' => 'Usuario no encontrado',
                    'status' => 404
                ];
            }

            // Verificar si el usuario es admin
            if ($user->rol === 'admin') {
                return [
                    'message' => 'No se puede eliminar un usuario administrador',
                    'status' => 403
                ];
            }

            $datosAnteriores = $user->toArray();

            // Si el usuario tiene una persona asociada
            if ($user->persona) {
                $persona = $user->persona;

                // 3. Eliminar la persona
                $persona->delete();
            }

            // 4. Finalmente eliminar el usuario
            $user->delete();

            AuditoriaService::registrar(
                'eliminar', 
                'usuarios', 
                $id, 
                $datosAnteriores, 
                null
            );

            DB::commit();

            return [
                'message' => 'Usuario eliminado con éxito',
                'status' => 200
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            return [
                'message' => 'Error al eliminar el usuario: ' . $e->getMessage(),
                'status' => 500
            ];
        }
    }

    public function update($id, array $data)
    {
        $user = User::with('persona')->find($id);

        $datosAnteriores = [
            'user' => $user->toArray(),
        ];

        if (!$user) {
            return [
                'message' => 'Usuario o Persona no encontrado',
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
            'user' => $user,
            'status' => 200
        ];
    }

    public function index(Request $request)
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
}