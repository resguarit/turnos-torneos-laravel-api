<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
   public function up(): void
    {
        Schema::table('clases', function (Blueprint $table) {
            $table->dropColumn('horario_id');
        });
    }

    public function down(): void
    {
        Schema::table('clases', function (Blueprint $table) {
            $table->unsignedBigInteger('horario_id')->nullable();
        });
    }
};
