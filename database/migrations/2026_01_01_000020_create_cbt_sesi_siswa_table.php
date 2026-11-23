<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cbt_sesi_siswa', function (Blueprint $table) {
            $table->integer('siswa_id');
            $table->integer('kelas_id')->nullable();
            $table->integer('ruang_id');
            $table->integer('sesi_id');
            $table->integer('tp_id');
            $table->integer('smt_id');
            $table->primary(['siswa_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cbt_sesi_siswa');
    }
};
