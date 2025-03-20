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
        Schema::table('partidos', function (Blueprint $table) {
            $table->unsignedBigInteger('equipo_local_id')->nullable()->after('fecha_id');
            $table->unsignedBigInteger('equipo_visitante_id')->nullable()->after('equipo_local_id');

            $table->foreign('equipo_local_id')->references('id')->on('equipos')->onDelete('cascade');
            $table->foreign('equipo_visitante_id')->references('id')->on('equipos')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('partidos', function (Blueprint $table) {
            $table->dropForeign(['equipo_local_id']);
            $table->dropForeign(['equipo_visitante_id']);
            $table->dropColumn(['equipo_local_id', 'equipo_visitante_id']);
        });
    }
};
