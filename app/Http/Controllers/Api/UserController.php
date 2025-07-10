<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Interface\UserServiceInterface;
use App\Services\Interface\AuthServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    protected $userService;
    protected $authService;

    public function __construct(
        UserServiceInterface $userService,
        AuthServiceInterface $authService
    ) {
        $this->userService = $userService;
        $this->authService = $authService;
    }

    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string',
            'email' => 'required|string|email|unique:users',
            'dni' => 'required|string|unique:users',
            'telefono' => 'required|numeric',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $validator->errors(),
                'status' => 422
            ], 422);
        }

        $subdominio = $request->header('x-complejo');

        $response = $this->authService->register($request->all(), $subdominio);
        return response()->json($response, $response['status']);
    }

    public function createUser(Request $request)
    {
        $authUser = Auth::user();
        abort_unless($authUser->rol === 'admin', 403, 'No tienes permisos para realizar esta acción');

        $validator = Validator::make($request->all(), [
            'name' => 'required|string',
            'email' => 'required|string|email|unique:users',
            'dni' => 'required|string|unique:users',
            'telefono' => 'required|numeric',
            'password' => 'required|string|min:8|confirmed',
            'rol' => 'required|string|in:cliente,moderador,admin'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $validator->errors(),
                'status' => 422
            ], 422);
        }

        $response = $this->userService->createUser($request);
        return response()->json($response, $response['status']);
    }

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'dni' => 'required_without:email|string',
            'email' => 'required_without:dni|email',
            'password' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $validator->errors(),
                'status' => 422
            ], 422);
        }

        $response = $this->authService->login($request->all());
        return response()->json($response, isset($response['token']) ? 200 : 401);
    }

    public function getUsers(Request $request)
    {
        $authUser = Auth::user();
        abort_unless($authUser->rol === 'admin', 403, 'No tienes permisos para realizar esta acción');

        $response = $this->userService->getUsers($request);
        return response()->json($response, $response['status']);
    }

    public function show($id)
    {
        $authUser = Auth::user();
        abort_unless($authUser->rol === 'admin' || $authUser->id == $id, 403, 'No tienes permisos para realizar esta acción');

        $response = $this->userService->show($id);
        return response()->json($response, $response['status']);
    }

    public function update(Request $request, $id)
    {
        $authUser = Auth::user();
        abort_unless($authUser->rol === 'admin' || $authUser->id == $id, 403, 'No tienes permisos para realizar esta acción');

        $validator = Validator::make($request->all(), [
            'name' => 'string',
            'email' => 'string|email|unique:users,email,' . $id,
            'dni' => 'string|unique:users,dni,' . $id,
            'telefono' => 'numeric',
            'password' => 'string|min:8|confirmed',
            'current_password' => 'required_with:password'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $validator->errors(),
                'status' => 422
            ], 422);
        }

        $response = $this->userService->update($id, $request->all());
        return response()->json($response, $response['status']);
    }

    public function destroy($id)
    {
        $authUser = Auth::user();
        abort_unless($authUser->rol === 'admin', 403, 'No tienes permisos para realizar esta acción');

        $response = $this->userService->destroy($id);
        return response()->json($response, $response['status']);
    }

    public function logout()
    {
        $user = Auth::user();
        $user->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Sesión cerrada con éxito',
            'status' => 200
        ], 200);
    }

    public function index(Request $request)
    {
        $user = Auth::user();
        abort_unless($user->tokenCan('usuario:show') || $user->rol === 'admin', 403, 'No tienes permisos para realizar esta acción');
        
        $result = $this->userService->index($request);
        return response()->json($result, $result['status'] ?? 200);
    }
}
