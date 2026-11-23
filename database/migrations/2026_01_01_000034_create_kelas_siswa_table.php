<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kelas_siswa', function (Blueprint $table) {
            $table->integer('id_kelas_siswa');
            $table->integer('id_tp');
            $table->integer('id_smt');
            $table->integer('id_siswa')->nullable();
            $table->integer('id_kelas')->nullable();
            $table->primary(['id_kelas_siswa']);
            $table->index(['id_siswa'], 'id_siswa_idx');
            $table->index(['id_kelas'], 'Id_kelas');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kelas_siswa');
    }
};
