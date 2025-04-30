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
        Schema::table('torneos', function (Blueprint $table) {
            $table->decimal('precio_inscripcion', 10, 2)->default(0)->after('año');
            $table->decimal('precio_por_fecha', 10, 2)->default(0)->after('precio_inscripcion');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('torneos', function (Blueprint $table) {
            $table->dropColumn(['precio_inscripcion', 'precio_por_fecha']);
        });
    }
};
