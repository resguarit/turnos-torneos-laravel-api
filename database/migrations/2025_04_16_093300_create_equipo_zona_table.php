<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Primero crear la tabla pivot
        Schema::create('equipo_zona', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipo_id')->constrained('equipos')->onDelete('cascade');
            $table->foreignId('zona_id')->constrained('zonas')->onDelete('cascade');
            $table->timestamps();
        });

        // Luego eliminar la columna zona_id de equipos
        Schema::table('equipos', function (Blueprint $table) {
            $table->dropForeign(['zona_id']);
            $table->dropColumn('zona_id');
        });
    }

    public function down()
    {
        // Primero restaurar la columna zona_id en equipos
        Schema::table('equipos', function (Blueprint $table) {
            $table->foreignId('zona_id')->nullable()->constrained('zonas');
        });

        // Luego eliminar la tabla pivot
        Schema::dropIfExists('equipo_zona');
    }
}; 