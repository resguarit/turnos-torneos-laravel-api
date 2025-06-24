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
        Schema::create('configuraciones', function (Blueprint $table) {
            $table->id();

            $table->json('colores')->comment('Ej: {"primary": "#ff0000", "secondary": "#00ff00"}');

            $table->boolean('habilitar_turnos')->default(true);

            $table->boolean('habilitar_mercado_pago')->default(false);
            $table->text('mercado_pago_access_token')->nullable()->comment('Credenciales encriptadas'); // Usar text para encriptar
            $table->text('mercado_pago_webhook_secret')->nullable()->comment('Credenciales encriptadas'); // Usar text para encriptar

            $table->string('direccion_complejo');
            $table->string('telefono_complejo')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('configuraciones');
    }
};
