<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('master_kelas', function (Blueprint $table) {
            $table->integer('id_kelas', true);
            $table->integer('id_tp');
            $table->integer('id_smt');
            $table->string('nama_kelas', 30);
            $table->string('kode_kelas', 20)->nullable();
            $table->integer('jurusan_id')->nullable();
            $table->integer('level_id');
            $table->integer('guru_id')->nullable();
            $table->integer('siswa_id')->nullable();
            $table->longText('jumlah_siswa')->nullable();
            $table->string('set_siswa', 1)->nullable()->default('0');
            $table->index(['level_id'], 'index_level_Id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('master_kelas');
    }
};
