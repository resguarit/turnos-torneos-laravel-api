<?php

namespace App\Services\Implementation;

use App\Models\Persona;
use App\Services\Interface\PersonaServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use App\Models\CuentaCorriente;

class PersonaService implements PersonaServiceInterface
{
    public function createPersona(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string',
            'dni' => 'required|string|unique:personas,dni',
            'telefono' => 'required|string',
            'direccion' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return [
                'message' => 'Error de validación',
                'errors' => $validator->errors(),
                'status' => 400
            ];
        }

        try {
            DB::beginTransaction();
    
            $persona = Persona::create([
                'name' => $request->name,
                'dni' => $request->dni,
                'telefono' => $request->telefono,
                'direccion' => $request->direccion ?? null,
            ]);
    
            $cuentaCorriente = CuentaCorriente::create([
                'persona_id' => $persona->id,
                'saldo' => 0,
            ]);
    
            DB::commit();
    
            return [
                'message' => 'Persona creada con éxito',
                'persona' => $persona,
                'cuenta_corriente' => $cuentaCorriente,
                'status' => 201
            ];
    
        } catch (\Exception $e) {
            DB::rollBack();
            
            return [
                'message' => 'Error al crear la persona y su cuenta corriente',
                'error' => $e->getMessage(),
                'status' => 500
            ];
        }
    }

    public function updatePersona(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string',
            'dni' => 'sometimes|string|unique:personas,dni,' . $id,
            'telefono' => 'sometimes|string',
            'direccion' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return [
                'message' => 'Error de validación',
                'errors' => $validator->errors(),
                'status' => 400
            ];
        }

        try {
            $persona = Persona::findOrFail($id);
        } catch (ModelNotFoundException $e) {
            return [
                'message' => 'Persona no encontrada',
                'status' => 404
            ];
        }
        // Actualizar el usuario asociado si existe
        $usuario = $persona->usuario;
        if ($usuario) {
            if ($request->has('dni')) {
                $usuario->dni = $request->dni;
            }
            $usuario->save();
        }

        $persona->fill($request->all());
        $persona->save();

        return [
            'message' => 'Persona actualizada con éxito',
            'status' => 200
        ];
    }

    public function deletePersona($id)
    {
        try {
            $persona = Persona::findOrFail($id);
        } catch (ModelNotFoundException $e) {
            return [
                'message' => 'Persona no encontrada',
                'status' => 404
            ];
        }

        $persona->delete();

        return [
            'message' => 'Persona eliminada con éxito',
            'status' => 200
        ];
    }

    public function restorePersona($id)
    {
        $persona = Persona::withTrashed()->find($id);

        if (!$persona) {
            return [
                'message' => 'Persona no encontrada',
                'status' => 404
            ];
        }

        $persona->restore();

        return [
            'message' => 'Persona restaurada con éxito',
            'status' => 200
        ];
    }

    public function showPersona($id)
    {
        try {
            $persona = Persona::findOrFail($id);
        } catch (ModelNotFoundException $e) {
            return [
                'message' => 'Persona no encontrada',
                'status' => 404
            ];
        }

        return [
            'data' => $persona,
            'status' => 200
        ];
    }

    public function getPersonas(Request $request)
    {
        $personas = Persona::query();

        if ($request->has('name')) {
            $personas->where('name', 'like', '%' . $request->name . '%');
        }

        if ($request->has('dni')) {
            $personas->where('dni', 'like', '%' . $request->dni . '%');
        }

        if ($request->has('telefono')) {
            $personas->where('telefono', 'like', '%' . $request->telefono . '%');
        }

        if ($request->has('direccion')) {
            $personas->where('direccion', 'like', '%' . $request->direccion . '%');
        }   

        return [
            'data' => $personas->get(),
            'status' => 200
        ];
    }
}