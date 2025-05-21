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
        Schema::create('bloqueo_disponibilidad_turnos', function (Blueprint $table) {
            $table->id();
            $table->date('fecha');
            $table->foreignId('cancha_id')->constrained('canchas');
            $table->foreignId('horario_id')->constrained('horarios');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bloqueo_disponibilidad_turnos');
    }
};
