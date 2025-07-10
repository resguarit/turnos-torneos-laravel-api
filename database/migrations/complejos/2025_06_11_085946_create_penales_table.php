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
        Schema::create('penales', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('partido_id')->nullable();
            $table->foreign('partido_id')->references('id')->on('partidos')->onDelete('set null');
            $table->unsignedBigInteger('equipo_local_id')->nullable();
            $table->foreign('equipo_local_id')->references('id')->on('equipos')->onDelete('set null');
            $table->unsignedBigInteger('equipo_visitante_id')->nullable();
            $table->foreign('equipo_visitante_id')->references('id')->on('equipos')->onDelete('set null');
            $table->integer('penales_local')->default(0);
            $table->integer('penales_visitante')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('penales');
    }
};
