<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rapor_nilai_ekstra', function (Blueprint $table) {
            $table->integer('id_nilai_ekstra', true);
            $table->integer('id_ekstra')->nullable();
            $table->integer('id_kelas')->nullable();
            $table->integer('id_siswa')->nullable();
            $table->integer('id_tp');
            $table->integer('id_smt');
            $table->integer('nilai');
            $table->string('predikat', 1)->nullable();
            $table->mediumText('deskripsi')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rapor_nilai_ekstra');
    }
};
