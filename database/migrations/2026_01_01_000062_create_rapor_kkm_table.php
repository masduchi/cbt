<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rapor_kkm', function (Blueprint $table) {
            $table->integer('id_kkm', true);
            $table->integer('kkm')->nullable()->default('0');
            $table->integer('bobot_ph')->nullable()->default('0');
            $table->integer('bobot_pts')->nullable()->default('0');
            $table->integer('bobot_pas')->nullable()->default('0');
            $table->integer('bobot_absen')->nullable()->default('0');
            $table->integer('beban_jam')->nullable()->default('0');
            $table->integer('id_tp')->default('0');
            $table->integer('id_smt')->default('0');
            $table->integer('jenis');
            $table->integer('id_kelas')->nullable();
            $table->integer('id_mapel')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rapor_kkm');
    }
};
