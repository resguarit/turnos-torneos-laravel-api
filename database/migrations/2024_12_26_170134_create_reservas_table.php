<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuarioID')->constrained('users')->onDelete('cascade');
            $table->foreignId('horarioCanchaID')->constrained('horarios_cancha')->onDelete('cascade');
            $table->date('fecha_reserva')->default(now());
            $table->date('fecha_turno');
            $table->decimal('monto_total',8,2);
            $table->decimal('monto_seña', 8, 2);
            $table->string('estado')->default('pendiente');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservas');
    }
};