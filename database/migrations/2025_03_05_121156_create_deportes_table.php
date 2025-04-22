<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Deporte;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('deportes', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->integer('jugadores_por_equipo');
            $table->timestamps();
        });

        Deporte::create([
            'nombre' => 'Futbol',
            'jugadores_por_equipo' => 5,
        ]);

        Deporte::create([
            'nombre' => 'Futbol',
            'jugadores_por_equipo' => 7,
        ]);

        Deporte::create([
            'nombre' => 'Futbol',
            'jugadores_por_equipo' => 11,
        ]);

        Deporte::create([
            'nombre' => 'Futbol',
            'jugadores_por_equipo' => 6,
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deportes');
    }
};
