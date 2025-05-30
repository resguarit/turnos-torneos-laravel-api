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
        Schema::table('turno_cancelaciones', function (Blueprint $table) {
            // Primero eliminar la foreign key constraint
            $table->dropForeign(['cancelado_por']);
            
            // Hacer el campo nullable
            $table->foreignId('cancelado_por')->nullable()->change();
            
            // Recrear la foreign key constraint permitiendo null
            $table->foreign('cancelado_por')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('turno_cancelaciones', function (Blueprint $table) {
            // Eliminar foreign key
            $table->dropForeign(['cancelado_por']);
            
            // Hacer el campo no nullable
            $table->foreignId('cancelado_por')->nullable(false)->change();
            
            // Recrear foreign key original
            $table->foreign('cancelado_por')->references('id')->on('users');
        });
    }
};
