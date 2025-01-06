<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBloqueoTemporalTable extends Migration
{
    public function up()
    {
        Schema::create('bloqueo_temporal', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('horario_id')->constrained('horarios')->onDelete('cascade');
            $table->foreignId('cancha_id')->constrained('canchas')->onDelete('cascade');
            $table->date('fecha');
            $table->timestamp('expira_en');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('bloqueo_temporal');
    }
}