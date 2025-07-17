<?php
// database/migrations/xxxx_xx_xx_xxxxxx_add_nro_camiseta_to_estadisticas_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddNroCamisetaToEstadisticasTable extends Migration
{
    public function up()
    {
        Schema::table('estadisticas', function (Blueprint $table) {
            $table->integer('nro_camiseta')->after('id');
        });
    }

    public function down()
    {
        Schema::table('estadisticas', function (Blueprint $table) {
            $table->dropColumn('nro_camiseta');
        });
    }
}