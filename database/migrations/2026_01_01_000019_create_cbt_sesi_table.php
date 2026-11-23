<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cbt_sesi', function (Blueprint $table) {
            $table->integer('id_sesi', true);
            $table->string('nama_sesi', 50);
            $table->string('kode_sesi', 10);
            $table->time('waktu_mulai');
            $table->time('waktu_akhir');
            $table->integer('aktif');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cbt_sesi');
    }
};
