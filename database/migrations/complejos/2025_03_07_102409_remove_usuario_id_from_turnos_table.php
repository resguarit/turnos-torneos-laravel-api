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
            // Eliminar la columna usuario_id
            $table->dropForeign(['usuario_id']);
            $table->dropColumn('usuario_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('turnos', function (Blueprint $table) {
            // Revertir los cambios en caso de rollback
            $table->foreignId('usuario_id')->constrained('users')->onDelete('cascade');
        });
    }
};
