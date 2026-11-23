<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cbt_kelas_ruang', function (Blueprint $table) {
            $table->string('id_kelas_ruang', 50);
            $table->integer('id_kelas')->nullable();
            $table->integer('id_ruang');
            $table->integer('id_sesi')->default('0');
            $table->integer('id_tp');
            $table->integer('id_smt');
            $table->integer('set_siswa')->default('0');
            $table->primary(['id_kelas_ruang']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cbt_kelas_ruang');
    }
};
