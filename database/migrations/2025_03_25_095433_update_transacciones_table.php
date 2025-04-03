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
        Schema::table('transacciones', function (Blueprint $table) {
            $table->unsignedBigInteger('caja_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transacciones', function (Blueprint $table) {
            $table->unsignedBigInteger('caja_id')->nullable(false)->change();
        });
    }
};
