<?php
// database/migrations/xxxx_xx_xx_xxxxxx_modify_nro_camiseta_nullable_in_estadisticas_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ModifyNullableNroCamisetaToEstadisticasTable extends Migration
{
    public function up()
    {
        Schema::table('estadisticas', function (Blueprint $table) {
            $table->integer('nro_camiseta')->nullable()->change();
        });
    }

    public function down()
    {
        Schema::table('estadisticas', function (Blueprint $table) {
            $table->integer('nro_camiseta')->nullable(false)->change();
        });
    }
}