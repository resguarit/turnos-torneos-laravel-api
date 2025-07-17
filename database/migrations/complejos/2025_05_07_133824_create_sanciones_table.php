<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Enums\TipoSancion;
use App\Enums\EstadoSancion;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sanciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipo_jugador_id')->constrained('equipo_jugador')->onDelete('cascade');
            $table->string('motivo');
            $table->string('tipo_sancion')->default(TipoSancion::EXPULSION->value); // Default to 'expulsión'
            $table->integer('cantidad_fechas')->nullable();
            $table->foreignId('fecha_inicio')->nullable()->constrained('fechas')->onDelete('set null'); // Relación con la tabla fechas
            $table->foreignId('fecha_fin')->nullable()->constrained('fechas')->onDelete('set null');
            $table->foreignId('partido_id')->nullable()->constrained('partidos')->onDelete('set null');
            $table->string('estado')->default(EstadoSancion::ACTIVA->value); // Default to 'activa'
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sanciones');
    }
};