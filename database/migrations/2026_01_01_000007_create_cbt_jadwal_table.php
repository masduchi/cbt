<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cbt_jadwal', function (Blueprint $table) {
            $table->integer('id_jadwal', true);
            $table->char('id_tp', 2);
            $table->char('id_smt', 2);
            $table->integer('id_bank')->nullable();
            $table->integer('id_jenis')->nullable();
            $table->string('tgl_mulai', 20);
            $table->string('tgl_selesai', 20);
            $table->integer('durasi_ujian');
            $table->longText('pengawas')->nullable();
            $table->integer('acak_soal');
            $table->integer('acak_opsi');
            $table->integer('hasil_tampil');
            $table->integer('token');
            $table->integer('status');
            $table->integer('ulang');
            $table->integer('reset_login');
            $table->integer('rekap')->default('0');
            $table->integer('jam_ke')->default('0');
            $table->integer('jarak')->default('0');
            $table->dateTime('time_create')->nullable()->useCurrentOnUpdate();
            $table->unique(['id_bank'], 'id_bank_soal');
            $table->index(['id_jenis'], 'idx_jns_fc');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cbt_jadwal');
    }
};
