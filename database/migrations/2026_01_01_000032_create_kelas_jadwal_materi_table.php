<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kelas_jadwal_materi', function (Blueprint $table) {
            $table->string('id_kjm', 50);
            $table->integer('id_tp');
            $table->integer('id_smt');
            $table->integer('id_materi')->nullable();
            $table->integer('id_mapel')->nullable();
            $table->integer('id_kelas')->nullable();
            $table->string('jadwal_materi', 20);
            $table->integer('jenis')->nullable()->comment('1=materi, 2=tugas');
            $table->primary(['id_kjm']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kelas_jadwal_materi');
    }
};
