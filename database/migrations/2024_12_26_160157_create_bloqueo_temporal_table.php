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
        Schema::create('bloqueo_temporal', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('horario_cancha_id')->constrained('horarios_cancha')->onDelete('cascade');
            $table->date('fecha');
            $table->timestamp('expira_en');
            $table->timestamps();

            $table->unique(['horario_cancha_id', 'fecha'], 'unique_horario_fecha');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bloqueo_temporal');
    }
};
