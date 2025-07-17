<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\User;
use App\Models\Persona;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Obtener todos los usuarios
        $usuarios = User::all();

        foreach ($usuarios as $usuario) {
            // Crear una nueva persona con los datos del usuario
            $persona = Persona::create([
                'name' => $usuario->name,
                'dni' => $usuario->dni,
                'telefono' => $usuario->telefono,
            ]);

            // Asignar el ID de la persona al usuario
            $usuario->persona_id = $persona->id;
            $usuario->save();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Persona::truncate();
    }
};
