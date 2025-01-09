<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('turnos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('horario_id')->constrained('horarios')->onDelete('cascade');
            $table->foreignId('cancha_id')->constrained('canchas')->onDelete('cascade');
            $table->date('fecha_reserva')->default(now());
            $table->date('fecha_turno');
            $table->decimal('monto_total',8,2);
            $table->decimal('monto_seña', 8, 2);
            $table->string('estado')->default('Pendiente');
            $table->string('tipo')->default('unico');
            $table->unique(['cancha_id', 'horario_id', 'fecha_turno'], 'unique_horario_fecha');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('turnos');
    }
};