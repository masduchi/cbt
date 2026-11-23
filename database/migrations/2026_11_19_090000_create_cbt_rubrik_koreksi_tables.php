<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rubrik penilaian esai per soal, penugasan korektor per jadwal, dan
     * catatan nilai tiap korektor (dasar perhitungan nilai akhir bersama).
     */
    public function up(): void
    {
        Schema::create('cbt_soal_rubrik', function (Blueprint $table) {
            $table->increments('id_rubrik');
            $table->integer('id_soal');
            $table->unsignedSmallInteger('urutan')->default(0);
            $table->string('kriteria', 200);
            $table->decimal('skor_maks', 6, 2)->default(0);

            $table->index('id_soal', 'cbt_soal_rubrik_soal_index');
        });

        Schema::create('cbt_koreksi', function (Blueprint $table) {
            $table->increments('id_koreksi');
            $table->string('id_soal_siswa', 15);
            $table->unsignedInteger('id_user');
            $table->decimal('skor', 6, 2)->default(0);
            $table->text('catatan')->nullable();
            $table->json('detail')->nullable();
            $table->boolean('final')->default(false);
            $table->timestamps();

            $table->unique(['id_soal_siswa', 'id_user'], 'cbt_koreksi_soal_user_unique');
            $table->index('id_soal_siswa', 'cbt_koreksi_soal_siswa_index');
            $table->index('id_user', 'cbt_koreksi_user_index');
        });

        Schema::create('cbt_koreksi_penilai', function (Blueprint $table) {
            $table->increments('id_penilai');
            $table->integer('id_jadwal');
            $table->integer('id_guru');

            $table->unique(['id_jadwal', 'id_guru'], 'cbt_koreksi_penilai_unique');
            $table->index('id_jadwal', 'cbt_koreksi_penilai_jadwal_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cbt_koreksi_penilai');
        Schema::dropIfExists('cbt_koreksi');
        Schema::dropIfExists('cbt_soal_rubrik');
    }
};
