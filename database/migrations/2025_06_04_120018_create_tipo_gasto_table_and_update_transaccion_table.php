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
        // Crear tabla tipos_gasto
        Schema::create('tipos_gasto', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100);
            $table->timestamps();
        });

        // Agregar campo tipo_gasto_id a la tabla transacciones
        Schema::table('transacciones', function (Blueprint $table) {
            $table->unsignedBigInteger('tipo_gasto_id')->nullable()->after('metodo_pago_id');
            $table->foreign('tipo_gasto_id')->references('id')->on('tipos_gasto')->onDelete('set null');
        });

        // Insertar algunos tipos de gasto predeterminados
        DB::table('tipos_gasto')->insert([
            ['nombre' => 'Alquiler', 'created_at' => now(), 'updated_at' => now()],
            ['nombre' => 'Servicios', 'created_at' => now(), 'updated_at' => now()],
            ['nombre' => 'Salarios', 'created_at' => now(), 'updated_at' => now()],
            ['nombre' => 'Mantenimiento', 'created_at' => now(), 'updated_at' => now()],
            ['nombre' => 'Insumos', 'created_at' => now(), 'updated_at' => now()],
            ['nombre' => 'Impuestos', 'created_at' => now(), 'updated_at' => now()],
            ['nombre' => 'Marketing', 'created_at' => now(), 'updated_at' => now()],
            ['nombre' => 'Otros', 'created_at' => now(), 'updated_at' => now()]
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Eliminar la relación de clave externa y el campo en transacciones
        Schema::table('transacciones', function (Blueprint $table) {
            $table->dropForeign(['tipo_gasto_id']);
            $table->dropColumn('tipo_gasto_id');
        });

        // Eliminar la tabla tipos_gasto
        Schema::dropIfExists('tipos_gasto');
    }
};
