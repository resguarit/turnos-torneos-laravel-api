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
            $table->unsignedBigInteger('deporte_id')->nullable()->after('año');
            $table->foreign('deporte_id')->references('id')->on('deportes')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('torneos', function (Blueprint $table) {
            $table->dropForeign(['deporte_id']);
            $table->dropColumn('deporte_id');
        });
    }
};
