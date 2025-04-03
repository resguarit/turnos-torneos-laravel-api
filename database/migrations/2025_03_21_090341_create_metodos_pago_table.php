<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\MetodoPago;
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('metodos_pago', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('descripcion')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        MetodoPago::create([
            'nombre' => 'Efectivo',
            'descripcion' => 'Pago en efectivo',
            'activo' => true,
        ]);

        MetodoPago::create([
            'nombre' => 'Tarjeta de Crédito',
            'descripcion' => 'Pago con tarjeta de crédito',
            'activo' => true,
        ]);

        MetodoPago::create([
            'nombre' => 'Transferencia Bancaria',
            'descripcion' => 'Pago por transferencia bancaria',
            'activo' => true,
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('metodos_pago');
    }
};
