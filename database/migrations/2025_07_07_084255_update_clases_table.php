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
        Schema::table('clases', function (Blueprint $table) {
            $table->dropColumn('fecha');
            $table->date('fecha_inicio')->nullable()->after('descripcion');
            $table->date('fecha_fin')->nullable()->after('fecha_inicio');
            $table->integer('duracion')->default(1)->after('horario_id'); // duración en horas
            $table->json('horario_ids')->nullable()->after('horario_id'); // para múltiples horarios
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clases', function (Blueprint $table) {
            $table->dropColumn(['fecha_inicio', 'fecha_fin', 'duracion', 'horario_ids']);
            $table->date('fecha')->nullable();
        });
    }
};
