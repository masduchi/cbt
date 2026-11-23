<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cbt_bank_soal', function (Blueprint $table) {
            $table->integer('id_bank', true);
            $table->integer('bank_jenis_id')->default('0');
            $table->string('bank_kode', 255)->default('0');
            $table->string('bank_level', 225);
            $table->longText('bank_kelas');
            $table->integer('bank_mapel_id')->nullable();
            $table->integer('bank_jurusan_id')->default('0');
            $table->integer('bank_guru_id')->nullable();
            $table->string('bank_nama', 250);
            $table->integer('kkm')->nullable()->default('0');
            $table->integer('jml_soal')->default('0');
            $table->integer('jml_esai')->default('0');
            $table->integer('tampil_pg')->default('0');
            $table->integer('tampil_esai')->default('0');
            $table->integer('bobot_pg')->default('0');
            $table->integer('bobot_esai')->default('0');
            $table->integer('opsi')->default('0');
            $table->timestamp('date')->useCurrent()->useCurrentOnUpdate();
            $table->integer('status')->default('0');
            $table->string('soal_agama', 20)->nullable();
            $table->integer('id_tp');
            $table->integer('id_smt');
            $table->longText('deskripsi')->nullable();
            $table->integer('jml_kompleks')->default('0');
            $table->integer('tampil_kompleks')->default('0');
            $table->integer('bobot_kompleks')->default('0');
            $table->integer('jml_jodohkan')->default('0');
            $table->integer('tampil_jodohkan')->default('0');
            $table->integer('bobot_jodohkan')->default('0');
            $table->integer('jml_isian')->default('0');
            $table->integer('tampil_isian')->default('0');
            $table->integer('bobot_isian')->default('0');
            $table->integer('status_soal')->default('0')->comment('0=belum selesai, 1=sudah selesai');
            $table->unique(['bank_kode'], 'kode_bank_soal');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cbt_bank_soal');
    }
};
