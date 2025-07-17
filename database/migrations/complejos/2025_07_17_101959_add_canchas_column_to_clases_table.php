<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('clases', function (Blueprint $table) {
            // Agregar nueva columna para array de canchas
            $table->json('cancha_ids')->nullable()->after('profesor_id');
        });
        
        // Migrar datos existentes de cancha_id a cancha_ids como array
        $clases = DB::table('clases')->whereNotNull('cancha_id')->get();
        
        foreach ($clases as $clase) {
            DB::table('clases')
                ->where('id', $clase->id)
                ->update(['cancha_ids' => json_encode([$clase->cancha_id])]);
        }
        
        // Eliminar foreign key y columna antigua
        Schema::table('clases', function (Blueprint $table) {
            if (Schema::hasColumn('clases', 'cancha_id')) {
                $table->dropForeign(['cancha_id']);
                $table->dropColumn('cancha_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clases', function (Blueprint $table) {
            // Restaurar columna cancha_id
            $table->foreignId('cancha_id')->nullable()->constrained('canchas')->onDelete('set null');
        });
        
        // Migrar primera cancha del array de vuelta a cancha_id
        $clases = DB::table('clases')->whereNotNull('cancha_ids')->get();
        
        foreach ($clases as $clase) {
            $canchaIds = json_decode($clase->cancha_ids, true);
            if (!empty($canchaIds)) {
                DB::table('clases')
                    ->where('id', $clase->id)
                    ->update(['cancha_id' => $canchaIds[0]]);
            }
        }
        
        Schema::table('clases', function (Blueprint $table) {
            $table->dropColumn('cancha_ids');
        });
    }
};
