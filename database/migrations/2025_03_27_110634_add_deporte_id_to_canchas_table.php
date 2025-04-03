<?php
// database/migrations/xxxx_xx_xx_xxxxxx_add_deporte_id_to_canchas_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDeporteIdToCanchasTable extends Migration
{
    public function up()
    {
        Schema::table('canchas', function (Blueprint $table) {
            $table->unsignedBigInteger('deporte_id')->nullable()->after('id'); // Agregar la columna deporte_id
            $table->foreign('deporte_id')->references('id')->on('deportes')->onDelete('cascade'); // Relación con deportes
        });
    }

    public function down()
    {
        Schema::table('canchas', function (Blueprint $table) {
            $table->dropForeign(['deporte_id']);
            $table->dropColumn('deporte_id');
        });
    }
}