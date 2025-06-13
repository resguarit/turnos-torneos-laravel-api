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
        Schema::dropIfExists('partido_ganadores');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Si quieres poder revertir la migración, puedes recrear la tabla aquí si lo deseas
        // De lo contrario, puedes dejarlo vacío
    }
};
