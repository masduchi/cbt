<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rapor_fisik', function (Blueprint $table) {
            $table->integer('id_fisik', true);
            $table->integer('id_kelas')->nullable();
            $table->integer('id_siswa')->nullable();
            $table->integer('id_tp');
            $table->integer('id_smt');
            $table->longText('kondisi');
            $table->integer('tinggi');
            $table->integer('berat');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rapor_fisik');
    }
};
