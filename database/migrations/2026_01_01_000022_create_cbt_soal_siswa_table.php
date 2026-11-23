<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cbt_soal_siswa', function (Blueprint $table) {
            $table->string('id_soal_siswa', 15);
            $table->integer('id_bank')->nullable();
            $table->integer('id_jadwal')->nullable();
            $table->integer('id_soal')->nullable();
            $table->integer('id_siswa')->nullable();
            $table->integer('jenis_soal');
            $table->integer('no_soal_alias');
            $table->string('opsi_alias_a', 1)->nullable();
            $table->string('opsi_alias_b', 1)->nullable();
            $table->string('opsi_alias_c', 1)->nullable();
            $table->string('opsi_alias_d', 1)->nullable();
            $table->string('opsi_alias_e', 1)->nullable();
            $table->longText('jawaban_alias')->nullable();
            $table->longText('jawaban_siswa')->nullable();
            $table->longText('jawaban_benar')->nullable();
            $table->integer('point_essai')->nullable()->default('0');
            $table->integer('soal_end')->default('0');
            $table->string('point_soal', 5)->default('0');
            $table->string('nilai_koreksi', 5)->default('0');
            $table->integer('nilai_otomatis')->default('0')->comment('0=otomatis, 1=dari guru');
            $table->dateTime('time_create')->nullable()->useCurrentOnUpdate();
            $table->primary(['id_soal_siswa']);
            $table->index(['id_siswa'], 'id_siswa');
            $table->index(['id_jadwal'], 'id_jadwal');
            $table->index(['id_soal'], 'id_soal_fc');
            $table->index(['id_bank'], 'id_bank_fc');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cbt_soal_siswa');
    }
};
