<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kelas_jadwal_mapel', function (Blueprint $table) {
            $table->integer('id_jadwal');
            $table->integer('id_tp');
            $table->integer('id_smt');
            $table->integer('id_kelas')->nullable();
            $table->integer('id_hari');
            $table->integer('jam_ke');
            $table->integer('id_mapel')->nullable();
            $table->primary(['id_jadwal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kelas_jadwal_mapel');
    }
};
