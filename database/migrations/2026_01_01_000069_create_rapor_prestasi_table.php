<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rapor_prestasi', function (Blueprint $table) {
            $table->integer('id_ranking', true);
            $table->integer('id_kelas')->nullable();
            $table->integer('id_siswa')->nullable();
            $table->integer('id_tp');
            $table->integer('id_smt');
            $table->integer('ranking');
            $table->string('deskripsi', 100);
            $table->string('p1', 100);
            $table->string('p1_desk', 100);
            $table->string('p2', 100);
            $table->string('p2_desk', 100);
            $table->string('p3', 100);
            $table->string('p3_desk', 100);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rapor_prestasi');
    }
};
