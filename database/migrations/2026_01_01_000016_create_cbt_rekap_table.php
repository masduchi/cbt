<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cbt_rekap', function (Blueprint $table) {
            $table->integer('id_rekap', true);
            $table->integer('id_tp');
            $table->string('tp', 20);
            $table->integer('id_smt');
            $table->string('smt', 20);
            $table->string('id_jadwal', 250);
            $table->string('id_jenis', 250);
            $table->string('kode_jenis', 20);
            $table->string('id_bank', 250);
            $table->longText('bank_kelas');
            $table->string('bank_kode', 20);
            $table->integer('bank_level');
            $table->string('id_mapel', 250);
            $table->string('nama_mapel', 100);
            $table->string('kode', 50);
            $table->string('tgl_mulai', 22);
            $table->string('tgl_selesai', 22);
            $table->integer('tampil_pg');
            $table->longText('jawaban_pg');
            $table->integer('tampil_esai');
            $table->longText('jawaban_esai');
            $table->integer('bobot_pg');
            $table->integer('bobot_esai');
            $table->string('id_guru', 250);
            $table->string('nama_guru', 100);
            $table->longText('nama_kelas')->nullable();
            $table->longText('soal_kompleks')->nullable();
            $table->longText('soal_jodohkan')->nullable();
            $table->longText('soal_isian')->nullable();
            $table->longText('soal_essai')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cbt_rekap');
    }
};
