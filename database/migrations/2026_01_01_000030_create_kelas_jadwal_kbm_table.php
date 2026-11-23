<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kelas_jadwal_kbm', function (Blueprint $table) {
            $table->integer('id_kbm');
            $table->integer('id_tp');
            $table->integer('id_smt');
            $table->integer('id_kelas')->nullable();
            $table->integer('kbm_jam_pel');
            $table->string('kbm_jam_mulai', 5);
            $table->integer('kbm_jml_mapel_hari');
            $table->mediumText('istirahat');
            $table->primary(['id_kbm']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kelas_jadwal_kbm');
    }
};
