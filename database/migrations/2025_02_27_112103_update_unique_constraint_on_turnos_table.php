<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('turnos', function (Blueprint $table) {
            // Agregar la nueva restricción única con la condición
            $table->unique(['cancha_id', 'horario_id', 'fecha_turno'], 'turnos_unique')->where('estado', '!=', 'Cancelado');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('turnos', function (Blueprint $table) {
            // Eliminar la nueva restricción única
            $table->dropUnique('turnos_unique');
        });
    }
};
