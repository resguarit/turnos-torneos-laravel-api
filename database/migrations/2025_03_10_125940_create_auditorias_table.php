<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auditorias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('accion'); // crear, modificar, eliminar, login, etc.
            $table->string('entidad'); // turnos, canchas, horarios, etc.
            $table->unsignedBigInteger('entidad_id')->nullable(); // ID del recurso afectado
            $table->longText('datos_antiguos')->nullable();
            $table->longText('datos_nuevos')->nullable();
            $table->string('ip')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('fecha_accion');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auditorias');
    }
};