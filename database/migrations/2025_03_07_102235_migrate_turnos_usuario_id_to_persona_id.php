<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Turno;
use App\Models\User;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Obtener todos los turnos
        $turnos = Turno::all();

        foreach ($turnos as $turno) {
            // Obtener el usuario asociado al turno
            $usuario = User::find($turno->usuario_id);

            if ($usuario && $usuario->persona_id) {
                // Asignar el persona_id correspondiente
                DB::table('turnos')
                    ->where('id', $turno->id)
                    ->update(['persona_id' => $usuario->persona_id]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('turnos')->update(['persona_id' => null]);
    }
};
