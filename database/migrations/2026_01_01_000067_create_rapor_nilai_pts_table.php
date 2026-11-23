<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rapor_nilai_pts', function (Blueprint $table) {
            $table->integer('id_nilai_pts', true);
            $table->integer('id_mapel')->nullable();
            $table->integer('id_kelas')->nullable();
            $table->integer('id_siswa')->nullable();
            $table->integer('id_tp');
            $table->integer('id_smt');
            $table->integer('nilai')->nullable()->default('0');
            $table->string('predikat', 1)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rapor_nilai_pts');
    }
};
