<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rapor_nilai_harian', function (Blueprint $table) {
            $table->integer('id_nilai_harian', true);
            $table->integer('id_siswa')->nullable();
            $table->integer('id_mapel')->nullable();
            $table->integer('id_kelas')->nullable();
            $table->integer('id_tp');
            $table->integer('id_smt');
            $table->string('p1', 3)->nullable();
            $table->string('p2', 3)->nullable();
            $table->string('p3', 3)->nullable();
            $table->string('p4', 3)->nullable();
            $table->string('p5', 3)->nullable();
            $table->string('p6', 3)->nullable();
            $table->string('p7', 3)->nullable();
            $table->string('p8', 3)->nullable();
            $table->string('p_rata_rata', 4)->nullable();
            $table->string('p_predikat', 1)->nullable();
            $table->longText('p_deskripsi')->nullable();
            $table->string('k1', 3)->nullable();
            $table->string('k2', 3)->nullable();
            $table->string('k3', 3)->nullable();
            $table->string('k4', 3)->nullable();
            $table->string('k5', 3)->nullable();
            $table->string('k6', 3)->nullable();
            $table->string('k7', 3)->nullable();
            $table->string('k8', 3)->nullable();
            $table->string('k_rata_rata', 4)->nullable();
            $table->string('k_predikat', 1)->nullable();
            $table->longText('k_deskripsi')->nullable();
            $table->integer('jml')->default('0');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rapor_nilai_harian');
    }
};
