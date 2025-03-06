<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class DropUniqueConstraintFromTurnos extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Paso 1: Eliminar la clave foránea
        Schema::table('turnos', function (Blueprint $table) {
            $table->dropForeign('turnos_cancha_id_foreign');
        });

        // Paso 2: Eliminar la restricción de unicidad
        Schema::table('turnos', function (Blueprint $table) {
            $table->dropUnique('turnos_cancha_id_horario_id_fecha_turno_estado_unique');
        });

        // Paso 3: Restaurar la clave foránea
        Schema::table('turnos', function (Blueprint $table) {
            $table->foreign('cancha_id')
                  ->references('id')
                  ->on('canchas')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Paso 1 (reversa): Eliminar la clave foránea
        Schema::table('turnos', function (Blueprint $table) {
            $table->dropForeign('turnos_cancha_id_foreign');
        });

        // Paso 2 (reversa): Restaurar la restricción de unicidad
        Schema::table('turnos', function (Blueprint $table) {
            $table->unique(['cancha_id', 'horario_id', 'fecha_turno', 'estado'], 'turnos_cancha_id_horario_id_fecha_turno_estado_unique');
        });

        // Paso 3 (reversa): Restaurar la clave foránea
        Schema::table('turnos', function (Blueprint $table) {
            $table->foreign('cancha_id')
                  ->references('id')
                  ->on('canchas')
                  ->onDelete('cascade');
        });
    }
}