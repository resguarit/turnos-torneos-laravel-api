<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // Verificar si la tabla pivote existe
        if (Schema::hasTable('equipo_zona')) {
            // Mover los equipos que tienen zona_id a la tabla pivote
            DB::statement('
                INSERT INTO equipo_zona (equipo_id, zona_id, created_at, updated_at)
                SELECT id, zona_id, NOW(), NOW()
                FROM equipos
                WHERE zona_id IS NOT NULL
            ');
        }
    }

    public function down()
    {
        // No hacemos nada en el down ya que los datos se moverán de vuelta
        // en la migración que elimina la tabla pivote
    }
}; 