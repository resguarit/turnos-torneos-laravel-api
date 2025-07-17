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
        Schema::table('users', function (Blueprint $table) {
            $table->softDeletes();
        });
        
        if (!Schema::hasColumn('canchas', 'deleted_at')) {
            Schema::table('canchas', function (Blueprint $table) {
                $table->softDeletes();
            });
        }

        if (!Schema::hasColumn('horarios', 'deleted_at')) {
            Schema::table('horarios', function (Blueprint $table) {
                $table->softDeletes();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
        
        Schema::table('canchas', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
        
        Schema::table('horarios', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
