<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cbt_rekap_nilai', function (Blueprint $table) {
            $table->integer('id_rekap_nilai', true);
            $table->integer('id_jadwal')->nullable();
            $table->integer('id_tp');
            $table->string('tp', 20);
            $table->integer('id_smt');
            $table->string('smt', 20);
            $table->integer('id_jenis');
            $table->string('kode_jenis', 20);
            $table->integer('id_bank')->nullable();
            $table->integer('id_mapel')->nullable();
            $table->integer('id_siswa')->nullable();
            $table->integer('id_kelas')->nullable()->default('0');
            $table->string('kelas', 20);
            $table->string('mulai', 20);
            $table->string('selesai', 20);
            $table->string('durasi', 20);
            $table->integer('bobot_pg');
            $table->longText('jawaban_pg');
            $table->string('nilai_pg', 10);
            $table->integer('bobot_esai');
            $table->longText('jawaban_esai');
            $table->string('nilai_esai', 10);
            $table->integer('id_guru')->nullable();
            $table->string('nama_siswa', 100)->nullable();
            $table->string('no_peserta', 100)->nullable();
            $table->longText('soal_kompleks')->nullable();
            $table->longText('soal_jodohkan')->nullable();
            $table->longText('soal_isian')->nullable();
            $table->longText('soal_essai')->nullable();
            $table->dateTime('time_create')->nullable()->useCurrentOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cbt_rekap_nilai');
    }
};
