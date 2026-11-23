<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cbt_nomor_peserta', function (Blueprint $table) {
            $table->integer('id_nomor');
            $table->integer('id_siswa')->nullable();
            $table->integer('id_tp');
            $table->integer('id_smt')->default('1');
            $table->string('nomor_peserta', 20);
            $table->primary(['id_nomor']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cbt_nomor_peserta');
    }
};
